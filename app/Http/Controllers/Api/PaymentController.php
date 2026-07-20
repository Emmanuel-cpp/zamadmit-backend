<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\AuditLogger;

/**
 * Mobile money application-fee payments.
 *
 * The provider interaction is SIMULATED but structured exactly like a real
 * MTN MoMo / Airtel Money integration:
 *
 *   initiate() = the "payment request" leg — creates a pending transaction
 *                and (in production) would call the provider's collection API
 *   confirm()  = the "callback" leg — in production this is the provider's
 *                webhook confirming the customer approved on their handset;
 *                here the frontend triggers it after the simulated PIN prompt
 *
 * Sandbox behaviour: any phone number ending in 9999 fails — the standard
 * magic-value pattern real payment sandboxes use for testing failure paths.
 *
 * Fee split (recorded on every row): ZamAdmit retains 5% of the
 * application fee as platform commission; the remainder is the
 * institution's. The rate lives in Payment::PLATFORM_RATE.
 */
class PaymentController extends Controller
{
    /**
     * POST /api/payments
     * Initiate a payment for a draft application owned by this student.
     */
    public function initiate(Request $request)
    {
        $data = $request->validate([
            'application_id' => 'required|integer|exists:applications,id',
            'provider'       => 'required|in:mtn,airtel,zamtel',
            'phone'          => 'required|string|min:10|max:15|regex:/^[0-9+]+$/',
        ]);

        $application = Application::where('id', $data['application_id'])
            ->where('user_id', $request->user()->id)   // ownership scope
            ->where('status', 'draft')                 // only unpaid drafts
            ->with('programme.institution')
            ->firstOrFail();

        // Don't stack pending payments on the same application —
        // return the existing one so the frontend can resume it.
        $existing = Payment::where('application_id', $application->id)
            ->where('status', 'pending')
            ->first();
        if ($existing) {
            return response()->json($existing);
        }

        // The fee belongs to the institution being applied to.
        $fee         = (float) ($application->programme->institution->application_fee ?? 150.00);
        $platformFee = round($fee * Payment::PLATFORM_RATE, 2);

        $payment = Payment::create([
            'application_id'     => $application->id,
            'user_id'            => $request->user()->id,
            'amount'             => $fee,
            'platform_fee'       => $platformFee,
            'institution_amount' => round($fee - $platformFee, 2),
            'provider'           => $data['provider'],
            'phone'              => $data['phone'],
            'status'             => 'pending',
            'reference'          => $this->generateReference(),
        ]);

        return response()->json($payment, 201);
    }

/**
     * POST /api/payments/{id}/confirm
     * The simulated provider callback. Capacity is checked inside the
     * completing transaction with a row lock, so a payment can never
     * succeed for a seat that no longer exists. Every outcome is audited.
     */
    public function confirm(Request $request, int $id)
    {
        $payment = Payment::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->with('application.programme')
            ->firstOrFail();

        // Sandbox failure path: phone ending 9999 always declines.
        if (str_ends_with($payment->phone, '9999')) {
            $payment->update(['status' => 'failed']);

            AuditLogger::log('payment.failed', $payment,
                new: ['reference' => $payment->reference, 'reason' => 'declined'],
                institutionId: $payment->application->programme->institution_id ?? null,
            );

            return response()->json([
                'payment' => $payment->fresh(),
                'message' => 'Payment declined by provider. Please check your balance and try again.',
            ], 402);
        }

        $result = DB::transaction(function () use ($payment) {
            $programme = \App\Models\Programme::where('id', $payment->application->programme_id)
                ->lockForUpdate()
                ->first();

            if ($programme->isFull()) {
                $payment->update(['status' => 'failed']);
                return 'full';
            }

            $payment->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            $payment->application->update([
                'status'       => 'submitted',
                'submitted_at' => now(),
            ]);

            AuditLogger::log('payment.completed', $payment,
                new: [
                    'amount'             => (string) $payment->amount,
                    'platform_fee'       => (string) $payment->platform_fee,
                    'institution_amount' => (string) $payment->institution_amount,
                    'reference'          => $payment->reference,
                ],
                institutionId: $programme->institution_id,
            );

            return 'ok';
        });

        if ($result === 'full') {
            AuditLogger::log('payment.failed', $payment,
                new: ['reference' => $payment->reference, 'reason' => 'programme_full'],
                institutionId: $payment->application->programme->institution_id ?? null,
            );

            return response()->json([
                'payment' => $payment->fresh(),
                'message' => 'This programme filled up just before your payment completed. You have NOT been charged. Your application remains saved as a draft.',
            ], 409);
        }

        return response()->json([
            'payment' => $payment->fresh(),
            'message' => 'Payment received. Your application has been submitted.',
        ]);
    }

    /**
     * GET /api/payments — the student's payment history.
     */
    public function index(Request $request)
    {
        $payments = Payment::where('user_id', $request->user()->id)
            ->with('application.programme.institution')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($payments);
    }

    /**
     * ZA- + 6 uppercase alphanumerics, collision-checked.
     */
    private function generateReference(): string
    {
        do {
            $ref = 'ZA-' . strtoupper(Str::random(6));
        } while (Payment::where('reference', $ref)->exists());

        return $ref;
    }
}
<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Payment;
use App\Services\PaymentSettlementService;
use Tests\TestCase;

class PaymentSettlementServiceTest extends TestCase
{
    public function test_it_explains_patient_charge_and_professional_net_amount(): void
    {
        config(['services.checkout.mindmeet_fee_rate' => 0.10]);

        $payment = new Payment([
            'amount' => 371,
            'charge_subtotal_amount' => 350,
            'total_charge_amount' => 371,
            'platform_fee_amount' => 21,
            'payment_method' => 'card',
            'stripe_payment_id' => 'pi_test',
            'status' => 'completed',
        ]);

        $breakdown = app(PaymentSettlementService::class)->breakdown($payment);

        $this->assertSame(371.0, $breakdown['patient_charge_amount']);
        $this->assertSame(350.0, $breakdown['session_amount']);
        $this->assertSame(21.0, $breakdown['stripe_fee_amount']);
        $this->assertSame(35.0, $breakdown['mindmeet_fee_amount']);
        $this->assertSame(315.0, $breakdown['net_psychologist_amount']);
    }

    public function test_payment_is_released_only_after_both_parties_complete_session(): void
    {
        $payment = new Payment([
            'amount' => 371,
            'payment_method' => 'card',
            'stripe_payment_id' => 'pi_test',
            'status' => 'completed',
        ]);
        $payment->setRelation('appointment', new Appointment([
            'statusUser' => 'Completed',
            'statusPatient' => 'Confirmed',
        ]));

        $service = app(PaymentSettlementService::class);
        $this->assertFalse($service->isWithdrawable($payment));

        $payment->appointment->statusPatient = 'Completed';
        $this->assertTrue($service->isWithdrawable($payment));
    }
}

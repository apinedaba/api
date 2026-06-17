# Stripe Connect Express para retiros de psicologos

## Objetivo

MindMeet cobra las sesiones al paciente en la cuenta Stripe de la plataforma. Despues, cada psicologo puede retirar su saldo a favor mediante Stripe Connect Express.

El dinero sigue este flujo:

```text
Paciente paga a MindMeet
-> Payment queda completed
-> Se calcula saldo disponible del psicologo con psychologist_amount
-> Psicologo solicita retiro
-> MindMeet crea Transfer a la cuenta conectada acct_...
-> MindMeet intenta crear Payout desde la cuenta conectada al banco del psicologo
-> Webhooks actualizan estados y logs
```

## Tablas nuevas

- `users`
  - `stripe_connect_account_id`: cuenta conectada `acct_...`.
  - `stripe_connect_onboarding_completed_at`: fecha en que Stripe habilito payouts.
  - `stripe_connect_charges_enabled`: estado informativo de cargos.
  - `stripe_connect_payouts_enabled`: requisito para permitir retiro.

- `professional_withdrawals`
  - Retiro solicitado por un psicologo.
  - Guarda monto, status, `stripe_transfer_id`, `stripe_payout_id`, errores y metadata.

- `professional_withdrawal_payments`
  - Liga cada retiro con los `payments` que financiaron ese retiro.
  - Permite evitar doble retiro de un mismo pago.
  - Soporta asignacion parcial de un payment.

- `stripe_transaction_logs`
  - Bitacora precisa de account links, account updates, transfers, payouts, webhooks y errores.

## Estados de retiro

- `requested`: retiro reservado internamente, antes o durante la llamada a Stripe.
- `transferred`: Stripe creo el `Transfer` hacia la cuenta conectada.
- `payout_created`: Stripe creo el `Payout` bancario desde la cuenta conectada.
- `paid`: Stripe confirmo `payout.paid`.
- `failed`: fallo la transferencia o payout.

## Calculo de saldo

El saldo disponible sale de `payments` y siempre considera el fee configurado de MindMeet.

- `user_id` igual al psicologo autenticado.
- `status = completed`.
- `amount > 0`.
- solo pagos cobrados por MindMeet desde el sitio web: deben tener `stripe_payment_id` y `payment_method` en `card`, `oxxo` o `stripe`.
- los pagos manuales quedan en historial, pero no generan saldo retirable porque el psicologo ya los cobro por fuera de MindMeet.
- monto base preferido: `psychologist_amount`.
- si `psychologist_amount` no existe pero `platform_fee_amount` existe, el neto es `amount - platform_fee_amount`.
- si el pago web de Stripe no tiene desglose historico, el neto se estima removiendo `services.checkout.platform_fee_rate`.
- se resta cualquier monto ya ligado en `professional_withdrawal_payments` cuyo retiro no este `failed`.

Esto evita que un pago se retire dos veces.

La tasa actual se lee de:

```php
config('services.checkout.platform_fee_rate')
```

Por defecto usa `MINDMEET_CHECKOUT_PLATFORM_FEE_RATE=0.06`.

## Endpoints del psicologo

Todos viven bajo autenticacion del profesional:

- `GET user/payouts/summary`
  - Devuelve estado Connect, saldo y retiros recientes.

- `POST user/payouts/connect/onboarding-link`
  - Crea o reutiliza cuenta Express.
  - Devuelve URL hosted de Stripe para completar datos fiscales/bancarios.

- `POST user/payouts/connect/refresh`
  - Consulta Stripe y actualiza flags locales.

- `POST user/payouts/withdraw`
  - Body:
    ```json
    {
      "amount": 1000,
      "auto_payout": true
    }
    ```
  - Crea `Transfer`.
  - Si `auto_payout` es true, intenta crear `Payout` al banco del psicologo.

## Webhooks esperados

El endpoint existente `/stripe/webhook` ahora tambien procesa:

- `account.updated`
- `transfer.created`
- `transfer.reversed`
- `transfer.failed`
- `payout.created`
- `payout.paid`
- `payout.failed`

Cada evento genera fila en `stripe_transaction_logs`.

## Operacion

1. Ejecutar migraciones:
   ```bash
   php artisan migrate
   ```

2. Confirmar que `STRIPE_SECRET_KEY` y webhook secret estan configurados.

3. En Stripe Dashboard, habilitar Connect Express para la plataforma.

4. En Stripe Dashboard, agregar al webhook los eventos Connect listados arriba.

5. El psicologo entra a `Perfil > Pagos`.

6. Da clic en `Configurar retiros`.

7. Stripe recopila identidad, datos fiscales y cuenta bancaria.

8. Cuando `payouts_enabled` sea true, MindMeet permite solicitar retiro.

## Auditoria

Para investigar un retiro:

1. Buscar en `professional_withdrawals` por `id`, `user_id`, `stripe_transfer_id` o `stripe_payout_id`.
2. Revisar sus pagos en `professional_withdrawal_payments`.
3. Revisar bitacora en `stripe_transaction_logs`.
4. Comparar IDs contra Stripe Dashboard:
   - `tr_...` para Transfer.
   - `po_...` para Payout.
   - `acct_...` para cuenta conectada.

## Nota importante

Stripe no permite mandar dinero directo desde MindMeet a una CLABE externa sin una cuenta conectada. El banco del psicologo se administra dentro de Stripe Connect Express y el payout sale desde esa cuenta conectada.

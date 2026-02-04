<?php

namespace App\Http\Controllers;

use App\Mail\SetPasswordMail;
use App\Mail\SubscriptionCancelledMail;
use App\Mail\SubscriptionRenewedMail;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    /**
     * Handle new purchase webhook from n8n/Hotmart
     */
    public function purchase(Request $request): JsonResponse
    {
        $log = WebhookLog::logWebhook('n8n', 'purchase', $request->all());

        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'name' => 'required|string',
                'product_id' => 'required|string',
                'offer_code' => 'nullable|string',
                'subscription_id' => 'nullable|string',
                'transaction_id' => 'nullable|string',
            ]);

            // First try to find plan by offer code (more specific)
            $plan = null;
            if (!empty($validated['offer_code'])) {
                $plan = Plan::findByOfferCode($validated['offer_code']);
            }

            // Fall back to product ID if no plan found by offer code
            if (!$plan) {
                $plan = Plan::findByHotmartProductId($validated['product_id']);
            }

            // Final fallback to basic plan
            if (!$plan) {
                $plan = Plan::where('slug', 'basico')->first();

                if (!$plan) {
                    throw new \Exception('No se encontró el plan básico');
                }
            }

            DB::beginTransaction();

            // Check if user already exists
            $user = User::where('email', $validated['email'])->first();

            if ($user) {
                // User exists - update their subscription
                $user->update([
                    'name' => $validated['name'],
                    'status' => 'active',
                    'tokens_balance' => $plan->tokens_monthly,
                    'tokens_used_month' => 0,
                ]);
            } else {
                // Create new user
                $passwordToken = Str::random(64);

                $user = User::create([
                    'email' => $validated['email'],
                    'name' => $validated['name'],
                    'status' => 'pending',
                    'tokens_balance' => $plan->tokens_monthly,
                    'tokens_used_month' => 0,
                    'password_token' => $passwordToken,
                    'password_token_expires_at' => Carbon::now()->addHours(48),
                ]);

                // Send set password email (with copy to admin)
                Mail::to($user->email)
                    ->bcc(config('mail.admin_copy', 'duver20000@gmail.com'))
                    ->queue(new SetPasswordMail($user, $passwordToken));
            }

            // Cancel any existing active subscriptions
            Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            // Create new subscription (duration based on plan)
            $durationMonths = $plan->duration_months ?? 1;
            Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'hotmart_subscription_id' => $validated['subscription_id'] ?? null,
                'hotmart_transaction_id' => $validated['transaction_id'] ?? null,
                'starts_at' => now(),
                'ends_at' => now()->addMonths($durationMonths),
            ]);

            // Clear plan cache so hasFreePlan() returns updated value
            $user->clearPlanCache();

            DB::commit();

            $log->markAsProcessed();

            return response()->json([
                'success' => true,
                'message' => 'Usuario procesado correctamente',
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $log->markAsFailed($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Error al procesar el webhook: ' . $e->getMessage() : 'Error al procesar el webhook',
            ], 500);
        }
    }

    /**
     * Handle cancellation webhook from n8n/Hotmart
     */
    public function cancel(Request $request): JsonResponse
    {
        $log = WebhookLog::logWebhook('n8n', 'cancel', $request->all());

        try {
            $validated = $request->validate([
                'email' => 'required_without:subscription_id|email',
                'subscription_id' => 'required_without:email|string',
            ]);

            DB::beginTransaction();

            // Find user by email or subscription
            if (isset($validated['email'])) {
                $user = User::where('email', $validated['email'])->first();
            } else {
                $subscription = Subscription::findByHotmartId($validated['subscription_id']);
                $user = $subscription?->user;
            }

            if (!$user) {
                throw new \Exception('Usuario no encontrado');
            }

            // Deactivate user
            $user->update(['status' => 'inactive']);

            // Cancel active subscription
            Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            // Clear plan cache so hasFreePlan() returns updated value
            $user->clearPlanCache();

            DB::commit();

            // Send cancellation notification email
            Mail::to($user->email)
                ->bcc(config('mail.admin_copy', 'duver20000@gmail.com'))
                ->queue(new SubscriptionCancelledMail($user));

            $log->markAsProcessed();

            return response()->json([
                'success' => true,
                'message' => 'Suscripción cancelada correctamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $log->markAsFailed($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Error al procesar la cancelación: ' . $e->getMessage() : 'Error al procesar la cancelación',
            ], 500);
        }
    }

    /**
     * Handle renewal webhook from n8n/Hotmart
     */
    public function renewal(Request $request): JsonResponse
    {
        $log = WebhookLog::logWebhook('n8n', 'renewal', $request->all());

        try {
            $validated = $request->validate([
                'email' => 'required_without:subscription_id|email',
                'subscription_id' => 'required_without:email|string',
            ]);

            DB::beginTransaction();

            // Find user by email or subscription
            if (isset($validated['email'])) {
                $user = User::where('email', $validated['email'])->first();
            } else {
                $subscription = Subscription::findByHotmartId($validated['subscription_id']);
                $user = $subscription?->user;
            }

            if (!$user) {
                throw new \Exception('Usuario no encontrado');
            }

            // Get user's active subscription to determine tokens
            $subscription = $user->activeSubscription;

            if (!$subscription) {
                throw new \Exception('No se encontró suscripción activa');
            }

            // Reset tokens for the new period
            $user->resetMonthlyTokens($subscription->plan->tokens_monthly);
            $user->update(['status' => 'active']);

            // Update subscription end date (based on plan duration)
            $durationMonths = $subscription->plan->duration_months ?? 1;
            $subscription->update([
                'ends_at' => now()->addMonths($durationMonths),
            ]);

            // Clear plan cache for consistency
            $user->clearPlanCache();

            DB::commit();

            // Send renewal notification email
            Mail::to($user->email)
                ->bcc(config('mail.admin_copy', 'duver20000@gmail.com'))
                ->queue(new SubscriptionRenewedMail($user, $user->tokens_balance));

            $log->markAsProcessed();

            return response()->json([
                'success' => true,
                'message' => 'Tokens renovados correctamente',
                'tokens_balance' => $user->tokens_balance,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $log->markAsFailed($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Error al procesar la renovación: ' . $e->getMessage() : 'Error al procesar la renovación',
            ], 500);
        }
    }

    /**
     * Handle refund webhook from n8n/Hotmart
     */
    public function refund(Request $request): JsonResponse
    {
        $log = WebhookLog::logWebhook('n8n', 'refund', $request->all());

        try { 
            $validated = $request->validate([
                'email' => 'required_without:subscription_id|email',
                'subscription_id' => 'required_without:email|string',
            ]); 

            DB::beginTransaction();

            // Find user by email or subscription
            if (isset($validated['email'])) {
                $user = User::where('email', $validated['email'])->first();
            } else {
                $subscription = Subscription::findByHotmartId($validated['subscription_id']);
                $user = $subscription?->user;
            }

            if (!$user) {
                throw new \Exception('Usuario no encontrado');
            }

            // Deactivate user
            $user->update(['status' => 'inactive']);

            // Cancel subscription
            Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            // Clear plan cache so hasFreePlan() returns updated value
            $user->clearPlanCache();

            DB::commit();

            $log->markAsProcessed();

            return response()->json([
                'success' => true,
                'message' => 'Reembolso procesado correctamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $log->markAsFailed($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? 'Error al procesar el reembolso: ' . $e->getMessage() : 'Error al procesar el reembolso',
            ], 500);
        }
    }
}

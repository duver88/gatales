<?php

namespace App\Listeners;

use App\Models\AiSetting;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LogSentEmail
{
    /**
     * Handle the event.
     */
    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->message;

            // Get email info from the message
            $to = $message->getTo();
            $toEmail = array_keys($to)[0] ?? null;
            $toName = $to[$toEmail] ?? null;
            $subject = $message->getSubject() ?? 'Sin asunto';

            if (!$toEmail) {
                return;
            }

            // Determine email type from subject or headers
            $type = $this->determineEmailType($subject);

            // Find user by email if possible
            $user = User::where('email', $toEmail)->first();

            // Get message ID from headers
            $messageId = null;
            foreach ($message->getHeaders()->all() as $header) {
                if ($header->getName() === 'Message-ID') {
                    $messageId = $header->getBodyAsString();
                    break;
                }
            }

            // Log the email
            EmailLog::create([
                'user_id' => $user?->id,
                'to_email' => $toEmail,
                'to_name' => $toName,
                'subject' => $subject,
                'type' => $type,
                'status' => EmailLog::STATUS_SENT,
                'provider' => config('mail.default'),
                'message_id' => $messageId,
                'sent_at' => now(),
            ]);

            // Send copy to supervision email if configured
            $this->sendSupervisionCopy($event, $toEmail, $subject);

        } catch (\Exception $e) {
            Log::error('Error logging sent email', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Determine email type from subject
     */
    private function determineEmailType(string $subject): string
    {
        $subjectLower = strtolower($subject);

        if (str_contains($subjectLower, 'restablecer') || str_contains($subjectLower, 'reset')) {
            return EmailLog::TYPE_PASSWORD_RESET;
        }

        if (str_contains($subjectLower, 'bienvenido') || str_contains($subjectLower, 'welcome') || str_contains($subjectLower, 'establece tu contraseña')) {
            return EmailLog::TYPE_WELCOME;
        }

        if (str_contains($subjectLower, 'suscripcion') || str_contains($subjectLower, 'subscription') || str_contains($subjectLower, 'renovada') || str_contains($subjectLower, 'cancelada')) {
            return EmailLog::TYPE_NOTIFICATION;
        }

        return EmailLog::TYPE_GENERAL;
    }

    /**
     * Send a copy of the email to supervision address
     */
    private function sendSupervisionCopy(MessageSent $event, string $originalTo, string $subject): void
    {
        try {
            // Get supervision email from settings
            $supervisionEmail = AiSetting::getValue('supervision_email');

            if (!$supervisionEmail || $supervisionEmail === $originalTo) {
                return;
            }

            // Validate email format
            if (!filter_var($supervisionEmail, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            // Get original message body
            $message = $event->message;
            $htmlBody = $message->getHtmlBody();
            $textBody = $message->getTextBody();

            // Create supervision wrapper with original info
            $supervisionInfo = "
                <div style='background:#f5f5f5;padding:15px;margin-bottom:20px;border-radius:5px;'>
                    <strong>COPIA DE SUPERVISION</strong><br>
                    <small>Destinatario original: {$originalTo}</small><br>
                    <small>Fecha: " . now()->format('Y-m-d H:i:s') . "</small>
                </div>
            ";

            // Send supervision copy
            Mail::raw($htmlBody ? '' : ($textBody ?? 'Email enviado'), function ($msg) use ($supervisionEmail, $subject, $originalTo, $supervisionInfo, $htmlBody, $textBody) {
                $msg->to($supervisionEmail)
                    ->subject("[SUPERVISION] {$subject} (para: {$originalTo})");

                if ($htmlBody) {
                    $msg->html($supervisionInfo . $htmlBody);
                }
            });

        } catch (\Exception $e) {
            Log::error('Error sending supervision email copy', [
                'error' => $e->getMessage(),
                'original_to' => $originalTo,
            ]);
        }
    }
}

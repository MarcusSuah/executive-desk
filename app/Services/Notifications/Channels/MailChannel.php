<?php

namespace App\Services\Notifications\Channels;

use App\Models\User;
use App\Services\Notifications\DeliveryResult;
use App\Services\Notifications\OutboundMessage;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Throwable;

class MailChannel implements DeliveryChannel
{
    /**
     * Determine whether the channel is configured on this installation.
     */
    public function isConfigured(): bool
    {
        return filled(config('mail.default'));
    }

    /**
     * Send the message's mailable synchronously; the caller is already a queued job.
     */
    public function send(User $user, OutboundMessage $message): DeliveryResult
    {
        try {
            $sent = Mail::to($user)->send($message->mailable);

            return DeliveryResult::sent($sent?->getMessageId());
        } catch (RfcComplianceException $exception) {
            return DeliveryResult::permanentFailure('Invalid email address: '.$exception->getMessage());
        } catch (TransportExceptionInterface $exception) {
            return DeliveryResult::transientFailure('Mail transport error: '.$exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return DeliveryResult::transientFailure('Unexpected mail error: '.class_basename($exception));
        }
    }
}

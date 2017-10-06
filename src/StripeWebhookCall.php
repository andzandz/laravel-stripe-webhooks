<?php

namespace Spatie\StripeWebhooks;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Spatie\StripeWebhooks\Exceptions\WebhookFailed;

class StripeWebhookCall extends Model
{
    public $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'exception' => 'array',
    ];

    public function process()
    {
        $this->clearException();

        if ($this->type === '') {
            throw WebhookFailed::missingType($this);
        }

        $jobClass = $this->determineJobClass($this->type);

        event("stripe-webhooks::{$this->type}", $this);

        if ($jobClass === '') {
            return;
        }

        if (! class_exists($jobClass)) {
            throw WebhookFailed::jobClassDoesNotExist($this);
        }

        dispatch(new $jobClass($this));
    }

    public function saveException(Exception $exception)
    {
        $this->exception = [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ];

        $this->save();

        return $this;
    }

    protected function determineJobClass(string $eventType): string
    {
        $jobConfigKey = str_replace('.', '_', $eventType);

        return config("stripe-webhooks.jobs.{$jobConfigKey}", '');
    }

    protected function clearException()
    {
        $this->exception = null;

        $this->save();

        return $this;
    }
}

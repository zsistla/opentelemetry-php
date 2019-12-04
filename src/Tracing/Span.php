<?php

declare(strict_types=1);

namespace OpenTelemetry\Tracing;

use Exception;

class Span
{
    private $name;
    private $spanContext;
    private $parentSpanContext;
    private $spanKind;
    private $start;
    private $end;
    private $statusCode;
    private $statusDescription;
    private $links;

    private $attributes = [];
    private $events = [];

    const SPANKIND_UNSPECIFIED = 0;
    const SPANKIND_INTERNAL = 1;
    const SPANKIND_SERVER = 2;
    const SPANKIND_CLIENT = 3;
    const SPANKIND_PRODUCER = 4;
    const SPANKIND_CONSUMER = 5;

    public function __construct(string $name, SpanContext $spanContext, SpanContext $parentSpanContext = null, int $spanKind  = self::SPANKIND_INTERNAL, iterable $links = [])
    {
        $this->name = $name;
        $this->spanContext = $spanContext;
        $this->parentSpanContext = $parentSpanContext;
        $this->spanKind == $spanKind;
        $this->start = microtime(true);
        $this->status = Status::OK;
        $this->statusDescription = null;
        $this->links = $links;
    }

    public function getLinks(): iterable
    {
        return $this->links;
    }

    // We are going to have this simple implementation for SpanKind until we see other use cases for it.
    public function getSpanKind(): int
    {
        return $this->spanKind;
    }

    public function getContext(): SpanContext
    {
        return clone $this->spanContext;
    }

    public function getParentContext(): ?SpanContext
    {
        // todo: Spec says a parent is a Span, SpanContext, or null -> should we implement this here?
        return $this->parentSpanContext !== null ? clone $this->parentSpanContext : null;
    }

    // We're not sure what we are going to need to do with these links now?
    public function addLinks()
    {;
    }

    public function end(int $statusCode = Status::OK, ?string $statusDescription = null, float $timestamp = null): self
    {
        $this->end = $timestamp ?? microtime(true);
        $this->statusCode = $statusCode;
        $this->statusDescription = null;
        return $this;
    }

    public function getStart()
    {
        return $this->start;
    }

    public function getEnd()
    {
        return $this->end;
    }

    public function getStatus(): Status
    {
        return Status::new($this->statusCode, $this->statusDescription);
    }

    // I think this is too simple, see: https://github.com/open-telemetry/opentelemetry-specification/blob/master/specification/api-tracing.md#isrecording 
    // -> This had an update this past month: https://github.com/open-telemetry/opentelemetry-specification/blob/master/specification/api-tracing.md#isrecording
    public function isRecording(): bool
    {
        return is_null($this->end);
    }

    public function getDuration(): ?float
    {
        if (!$this->end) {
            return null;
        }
        return $this->end - $this->start;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function updateName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getAttribute(string $key)
    {
        if (!array_key_exists($key, $this->attributes)) {
            return null;
        }
        return $this->attributes[$key];
    }

    public function setAttribute(string $key, $value): self
    {
        if (!is_string($value) || !is_bool($value) || !is_int($value)) {
            $this->throwIfNotRecording();
        }

        $this->attributes[$key] = $value;
        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(iterable $attributes): self
    {
        $this->throwIfNotRecording();

        $this->attributes = [];
        foreach ($attributes as $k => $v) {
            $this->setAttribute($k, $v);
        }
        return $this;
    }

    // todo: is accepting an Iterator enough to satisfy AddLazyEvent?  -> Looks like the spec might have been updated here: https://github.com/open-telemetry/opentelemetry-specification/blob/master/specification/api-tracing.md#add-events
    public function addEvent(string $name, iterable $attributes = [], float $timestamp = null): Event
    {
        $this->throwIfNotRecording();

        $event = new Event($name, $attributes, $timestamp);
        // todo: check that these are all Attributes -> What do we want to check about these?  Just a 'property_exist' check on this?
        $this->events[] = $event;
        return $event;
    }

    public function getEvents()
    {
        return $this->events;
    }

    /* A Span is said to have a remote parent if it is the child of a Span
     * created in another process. Each propagators' deserialization must set IsRemote to true on a parent
     *  SpanContext so Span creation knows if the parent is remote. */
    public function IsRemote(): bool
    {;
    }

    private function throwIfNotRecording()
    {
        if (!$this->isRecording()) {
            throw new Exception("Span is readonly");
        }
    }
}

<?php

namespace Amp;

use Interop\Async\Loop;

/**
 * Creates an observable that emits values emitted from any observable in the array of observables. Values in the
 * array are passed through the from() function, so they may be observables, arrays of values to emit, awaitables,
 * or any other value.
 *
 * @param \Amp\Observable[] $observables
 *
 * @return \Amp\Observable
 */
function merge(array $observables) {
    foreach ($observables as $observable) {
        if (!$observable instanceof Observable) {
            throw new \InvalidArgumentException("Non-observable provided");
        }
    }

    $postponed = new Postponed;

    $subscriptions = [];

    foreach ($observables as $observable) {
        $subscriptions[] = $observable->subscribe([$postponed, 'emit']);
    }

    all($subscriptions)->when(function ($exception, $value) use ($postponed) {
        if ($exception) {
            $postponed->fail($exception);
            return;
        }

        $postponed->complete($value);
    });

    return $postponed->getObservable();
}

/**
 * Returns an observable that emits a value every $interval milliseconds after the previous value has been consumed
 * (up to $count times (or indefinitely if $count is 0). The value emitted is an integer of the number of times the
 * observable emitted a value.
 *
 * @param int $interval Time interval between emitted values in milliseconds.
 * @param int $count Use 0 to emit values indefinitely.
 *
 * @return \Amp\Observable
 */
function interval($interval, $count = 0) {
    $count = (int) $count;
    if (0 > $count) {
        throw new \InvalidArgumentException("The number of times to emit must be a non-negative value");
    }

    $postponed = new Postponed;

    Loop::repeat($interval, function ($watcher) use (&$i, $postponed, $count) {
        $postponed->emit(++$i);

        if ($i === $count) {
            Loop::cancel($watcher);
            $postponed->complete();
        }
    });

    return $postponed->getObservable();
}

/**
 * @param int $start
 * @param int $end
 * @param int $step
 *
 * @return \Amp\Observable
 */
function range($start, $end, $step = 1) {
    $start = (int) $start;
    $end = (int) $end;
    $step = (int) $step;

    if (0 === $step) {
        throw new \InvalidArgumentException("Step must be a non-zero integer");
    }

    if ((($end - $start) ^ $step) < 0) {
        throw new \InvalidArgumentException("Step is not of the correct sign");
    }

    $postponed = new Postponed;

    $generator = function (Postponed $postponed, $start, $end, $step) {
        for ($i = $start; $i <= $end; $i += $step) {
            yield $postponed->emit($i);
        }
    };

    $coroutine = new Coroutine($generator($postponed, $start, $end, $step));
    $coroutine->when(function ($exception) use ($postponed) {
        if ($exception) {
            $postponed->fail($exception);
            return;
        }

        $postponed->complete();
    });

    return $postponed->getObservable();
}

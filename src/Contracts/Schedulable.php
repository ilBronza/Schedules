<?php

namespace IlBronza\Schedules\Contracts;

interface Schedulable
{
    /**
     * Allowlisted methods that may be used to read schedule values.
     *
     * @return list<string>
     */
    public function getAllowedScheduleValueMethods(): array;

    /**
     * Resolve the current schedule value for an allowlisted method.
     *
     * Implementations must refuse any method that is not allowlisted.
     */
    public function getScheduleCurrentValue(string $method): mixed;
}

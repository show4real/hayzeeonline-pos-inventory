<?php

namespace App\Concerns;

use DateTimeInterface;

/**
 * Keeps JSON dates in the format the POS UI already parses.
 *
 * Laravel 6 serialized a date cast as `$date->format($this->getDateFormat())`,
 * which for MySQL is 'Y-m-d H:i:s' — "2026-10-22 09:13:00". Laravel 7 changed
 * the default to `$date->toJSON()`, an ISO-8601 string in UTC with a trailing
 * Z — "2026-10-22T09:13:00.000000Z".
 *
 * That difference is not cosmetic here. The UI renders these fields with
 * `moment(value).format('MMM DD YYYY')`. Moment reads the naive form as local
 * time and the Z form as UTC, converting it: on WAT (UTC+1) every timestamp
 * shifts an hour, and anything stored between 00:00 and 01:00 renders as the
 * previous day — wrong dates on invoices, stock receipts and payments.
 *
 * The rest of the upgrade keeps the API's response shape unchanged; this keeps
 * the values in it unchanged too. Delete this trait only together with a
 * deliberate change to how the UI parses dates.
 */
trait SerializesDatesForApi
{
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format($this->getDateFormat());
    }
}

<?php

namespace App\Events;

use App\Models\AMLScreening;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sprint 4 — fired when an AML screening yields a sanctions / PEP /
 * adverse-media match (risk_level >= medium). Triggers:
 *   - SendKYCNotification         → email the compliance officer
 *   - ReportSuspiciousActivity    → auto-emit a CENTIF declaration if
 *                                   sanctions match OR risk_level=critical
 */
class AMLFlagged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public AMLScreening $screening,
    ) {}
}

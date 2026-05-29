<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * MediTrack authenticates via DEORIS portal headers / SSO — no local users table.
 */
class PortalAccount extends Authenticatable
{
    protected $table = 'sessions';
}

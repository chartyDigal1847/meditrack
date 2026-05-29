<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('clinic.notifications', fn ($user) => (bool) $user);
Broadcast::channel('medical.events', fn ($user) => in_array($user->role, ['nurse', 'admin'], true));
Broadcast::channel('clinic.emergency-alerts', fn ($user) => in_array($user->role, ['nurse', 'admin', 'student'], true));
Broadcast::channel('student.health.{externalId}', fn ($user, string $externalId) => $user->role !== 'student' || $user->external_id === $externalId);

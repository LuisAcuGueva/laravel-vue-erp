<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Audit;

use App\Modules\Audit\Models\Activity;
use App\Modules\Users\Models\User;
use App\Support\Audit\AuditContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityBranchAndSessionContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_id_resolved_from_subject_when_context_is_empty(): void
    {
        $branchIdFromSubject = (string) Str::uuid();

        // Modelo dummy anónimo con branch_id, para probar subject->getAttribute('branch_id')
        // sin depender de que Branch tenga esa columna (su clave es id, no branch_id).
        $dummySubject = new class extends Model
        {
            use HasUuids;

            public $incrementing = false;

            protected $table = 'branches';

            public function getAttribute($key)
            {
                if ($key === 'branch_id') {
                    return $this->attributes['branch_id'] ?? null;
                }

                return parent::getAttribute($key);
            }
        };
        $dummySubject->setAttribute('branch_id', $branchIdFromSubject);

        activity()
            ->performedOn($dummySubject)
            ->log('dummy activity with subject branch');

        /** @var Activity $subjectActivity */
        $subjectActivity = Activity::latest('id')->firstOrFail();

        $this->assertSame($branchIdFromSubject, $subjectActivity->branch_id);
    }

    public function test_session_id_present_when_audit_context_has_session(): void
    {
        $sessionId = (string) Str::uuid();

        $context = app(AuditContext::class);
        $context->setSessionId($sessionId);

        $user = User::factory()->create();

        activity()
            ->performedOn($user)
            ->log('user action in session');

        /** @var Activity $activity */
        $activity = Activity::latest('id')->firstOrFail();

        $this->assertSame($sessionId, $activity->session_id);
    }

    public function test_fallback_to_audit_context_branch_when_subject_has_no_branch_id(): void
    {
        $branchId = (string) Str::uuid();

        $context = app(AuditContext::class);
        $context->setBranchId($branchId);

        $user = User::factory()->create();

        activity()
            ->performedOn($user)
            ->log('user action with branch context');

        /** @var Activity $activity */
        $activity = Activity::latest('id')->firstOrFail();

        $this->assertSame($branchId, $activity->branch_id);
    }
}

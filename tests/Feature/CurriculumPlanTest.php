<?php

namespace Tests\Feature;

use App\Models\CurriculumPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumPlanTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем администратора
        $this->admin = User::factory()->create(['role_id' => 1]); // Предполагаем, что role_id 1 - администратор
    }

    public function test_admin_can_create_curriculum_plan()
    {
        $response = $this->actingAs($this->admin)->post('/api/curriculum-plans', [
            'school_class_id' => 1,
            'subject_id' => 1,
            'academic_year' => '2024-2025',
            'hours_per_week' => 3,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('curriculum_plans', [
            'school_class_id' => 1,
            'subject_id' => 1,
            'academic_year' => '2024-2025',
            'hours_per_week' => 3,
        ]);
    }

    public function test_admin_can_get_all_curriculum_plans()
    {
        // Создаем несколько учебных планов
        CurriculumPlan::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get('/api/curriculum-plans');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_admin_can_get_curriculum_plan_by_id()
    {
        $plan = CurriculumPlan::factory()->create();

        $response = $this->actingAs($this->admin)->get("/api/curriculum-plans/{$plan->id}");

        $response->assertStatus(200);
        $response->assertJson(['id' => $plan->id]);
    }

    public function test_admin_can_update_curriculum_plan()
    {
        $plan = CurriculumPlan::factory()->create([
            'academic_year' => '2023-2024',
            'hours_per_week' => 2,
        ]);

        $response = $this->actingAs($this->admin)->put("/api/curriculum-plans/{$plan->id}", [
            'academic_year' => '2024-2025',
            'hours_per_week' => 3,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('curriculum_plans', [
            'id' => $plan->id,
            'academic_year' => '2024-2025',
            'hours_per_week' => 3,
        ]);
    }

    public function test_admin_can_delete_curriculum_plan()
    {
        $plan = CurriculumPlan::factory()->create();

        $response = $this->actingAs($this->admin)->delete("/api/curriculum-plans/{$plan->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('curriculum_plans', ['id' => $plan->id]);
    }

    public function test_admin_can_get_curriculum_plans_by_class()
    {
        $classId = 1;

        // Создаем планы для разных классов
        CurriculumPlan::factory()->create(['school_class_id' => $classId]);
        CurriculumPlan::factory()->create(['school_class_id' => 2]);

        $response = $this->actingAs($this->admin)->get("/api/curriculum-plans/class/{$classId}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'curriculum_plans');
    }

    public function test_admin_can_get_curriculum_plans_by_subject()
    {
        $subjectId = 1;

        // Создаем планы для разных предметов
        CurriculumPlan::factory()->create(['subject_id' => $subjectId]);
        CurriculumPlan::factory()->create(['subject_id' => 2]);

        $response = $this->actingAs($this->admin)->get("/api/curriculum-plans/subject/{$subjectId}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'curriculum_plans');
    }

    public function test_cannot_create_duplicate_curriculum_plan()
    {
        // Создаем учебный план
        CurriculumPlan::factory()->create([
            'school_class_id' => 1,
            'subject_id' => 1,
            'academic_year' => '2024-2025',
        ]);

        // Пытаемся создать дубликат
        $response = $this->actingAs($this->admin)->post('/api/curriculum-plans', [
            'school_class_id' => 1,
            'subject_id' => 1,
            'academic_year' => '2024-2025',
            'hours_per_week' => 3,
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Учебный план для данного класса, предмета и учебного года уже существует']);
    }

    public function test_validation_for_curriculum_plan_creation()
    {
        $response = $this->actingAs($this->admin)->post('/api/curriculum-plans', [
            'school_class_id' => '',
            'subject_id' => '',
            'academic_year' => '',
            'hours_per_week' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['school_class_id', 'subject_id', 'academic_year', 'hours_per_week']);
    }
}

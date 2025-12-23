<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurriculumPlan;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Models\ThematicBlock;
use App\Models\AcademicYear;
use App\Models\AcademicWeek;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CurriculumPlanController extends Controller
{
    /**
     * Получить список учебных планов с фильтрацией
     */
    public function index(Request $request)
    {
        try {
            $query = CurriculumPlan::with([
                'schoolClass:id,name,year,letter',
                'subject:id,name,short_name',
                'teacher:id,name,surname,second_name'
            ]);

            // Фильтрация по классу
            if ($request->has('school_class_id') && !empty($request->school_class_id)) {
                $query->where('school_class_id', $request->school_class_id);
            }

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по учебному году
            if ($request->has('academic_year') && !empty($request->academic_year)) {
                $query->where('academic_year', $request->academic_year);
            }

            // Сортировка
            $sortBy = $request->get('sort_by', 'academic_year');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $curriculumPlans = $query->paginate(20);

            return response()->json([
                'data' => $curriculumPlans->items(),
                'pagination' => [
                    'current_page' => $curriculumPlans->currentPage(),
                    'last_page' => $curriculumPlans->lastPage(),
                    'per_page' => $curriculumPlans->perPage(),
                    'total' => $curriculumPlans->total()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error in CurriculumPlanController::index", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении учебных планов'], 500);
        }
    }

    /**
     * Создать новый учебный план
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'school_class_id' => 'required|exists:school_classes,id',
                'subject_id' => 'required|exists:subjects,id',
                'teacher_id' => 'nullable|exists:users,id',
                'academic_year' => 'required|string|max:20',
                'hours_per_week' => 'required|integer|min:1|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Проверяем, что плана для данного класса, предмета и учебного года еще не существует
            $existingPlan = CurriculumPlan::where([
                'school_class_id' => $request->school_class_id,
                'subject_id' => $request->subject_id,
                'academic_year' => $request->academic_year
            ])->first();

            if ($existingPlan) {
                return response()->json(['error' => 'Учебный план для данного класса, предмета и учебного года уже существует'], 400);
            }

            $curriculumPlan = CurriculumPlan::create($validator->validated());

            // Загружаем связанные данные
            $curriculumPlan->load(['schoolClass:id,name,year,letter', 'subject:id,name,short_name', 'teacher:id,name,surname,second_name']);

            Log::info('Curriculum plan created successfully', [
                'plan_id' => $curriculumPlan->id,
                'school_class_id' => $curriculumPlan->school_class_id,
                'subject_id' => $curriculumPlan->subject_id,
                'academic_year' => $curriculumPlan->academic_year
            ]);

            return response()->json($curriculumPlan, 201);

        } catch (\Exception $e) {
            Log::error('Error creating curriculum plan', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Ошибка при создании учебного плана'], 500);
        }
    }

    /**
     * Получить детали конкретного учебного плана
     */
    public function show($id)
    {
        try {
            $curriculumPlan = CurriculumPlan::with([
                'schoolClass:id,name,year,letter',
                'subject:id,name,short_name,description',
                'teacher:id,name,surname,second_name'
            ])->findOrFail($id);

            return response()->json($curriculumPlan);

        } catch (\Exception $e) {
            Log::error("Error in CurriculumPlanController::show", [
                'plan_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Учебный план не найден'], 404);
        }
    }

    /**
     * Обновить учебный план
     */
    public function update(Request $request, $id)
    {
        try {
            $curriculumPlan = CurriculumPlan::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'teacher_id' => 'sometimes|nullable|exists:users,id',
                'academic_year' => 'sometimes|required|string|max:20',
                'hours_per_week' => 'sometimes|required|integer|min:1|max:10',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $curriculumPlan->update($validator->validated());
            $curriculumPlan->load(['schoolClass:id,name,year,letter', 'subject:id,name,short_name', 'teacher:id,name,surname,second_name']);

            Log::info('Curriculum plan updated successfully', [
                'plan_id' => $curriculumPlan->id,
                'updated_fields' => $request->only(['academic_year', 'hours_per_week'])
            ]);

            return response()->json($curriculumPlan);

        } catch (\Exception $e) {
            Log::error('Error updating curriculum plan', [
                'plan_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при обновлении учебного плана'], 500);
        }
    }

    /**
     * Удалить учебный план
     */
    public function destroy($id)
    {
        try {
            $curriculumPlan = CurriculumPlan::findOrFail($id);
            $curriculumPlan->delete();

            Log::info('Curriculum plan deleted successfully', [
                'plan_id' => $id
            ]);

            return response()->json(['message' => 'Учебный план успешно удален']);

        } catch (\Exception $e) {
            Log::error('Error deleting curriculum plan', [
                'plan_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при удалении учебного плана'], 500);
        }
    }

    /**
     * Получить учебные планы для класса
     */
    public function byClass($classId)
    {
        try {
            $schoolClass = SchoolClass::findOrFail($classId);

            $curriculumPlans = CurriculumPlan::with([
                'subject:id,name,short_name',
                'teacher:id,name,surname,second_name'
            ])->where('school_class_id', $classId)
              ->orderBy('academic_year', 'desc')
              ->get();

            return response()->json([
                'class' => $schoolClass->only(['id', 'name', 'grade']),
                'curriculum_plans' => $curriculumPlans
            ]);

        } catch (\Exception $e) {
            Log::error("Error in CurriculumPlanController::byClass", [
                'school_class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Класс не найден или ошибка при получении планов'], 404);
        }
    }

    /**
     * Получить учебные планы для предмета
     */
    public function bySubject($subjectId)
    {
        try {
            $subject = Subject::findOrFail($subjectId);

            $curriculumPlans = CurriculumPlan::with([
                'schoolClass:id,name,year,letter',
                'teacher:id,name,surname,second_name'
            ])->where('subject_id', $subjectId)
              ->orderBy('academic_year', 'desc')
              ->get();

            return response()->json([
                'subject' => $subject->only(['id', 'name', 'short_name']),
                'curriculum_plans' => $curriculumPlans
            ]);

        } catch (\Exception $e) {
            Log::error("Error in CurriculumPlanController::bySubject", [
                'subject_id' => $subjectId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Предмет не найден или ошибка при получении планов'], 404);
        }
    }

    /**
     * Получить учебные планы для учителя
     */
    public function byTeacher($teacherId)
    {
        try {
            $teacher = User::findOrFail($teacherId);

            $curriculumPlans = CurriculumPlan::with([
                'schoolClass:id,name,year,letter',
                'subject:id,name,short_name'
            ])->where('teacher_id', $teacherId)
              ->orderBy('academic_year', 'desc')
              ->get();

            return response()->json([
                'teacher' => $teacher->only(['id', 'name', 'surname', 'second_name']),
                'curriculum_plans' => $curriculumPlans
            ]);

        } catch (\Exception $e) {
            Log::error("Error in CurriculumPlanController::byTeacher", [
                'teacher_id' => $teacherId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Учитель не найден или ошибка при получении планов'], 404);
        }
    }
}

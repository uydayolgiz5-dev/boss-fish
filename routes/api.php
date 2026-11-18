<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogProductController;
use App\Http\Controllers\Api\CompanyHrController;
use App\Http\Controllers\Api\DirectorCabinetController;
use App\Http\Controllers\Api\DirectorFinanceController;
use App\Http\Controllers\Api\OwnerCabinetController;
use App\Http\Controllers\Api\OwnerExperienceController;
use App\Http\Controllers\Api\OwnerPlannerController;
use App\Http\Controllers\Api\OwnerProjectController;
use App\Http\Controllers\Api\SuperadminController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\VengerCabinetController;
use App\Http\Controllers\Api\VengerClientController;
use App\Http\Controllers\Api\VengerFeedPlanController;
use App\Http\Controllers\Api\WorkerCabinetController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/owner-login', [AuthController::class, 'ownerLogin']);
    Route::post('/venger-login', [AuthController::class, 'vengerLogin']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth:sanctum', 'superadmin'])->prefix('superadmin')->group(function () {
    Route::get('/dashboard', [SuperadminController::class, 'dashboard']);
    Route::get('/pricing-plans', [SuperadminController::class, 'pricingPlans']);
    Route::get('/companies', [SuperadminController::class, 'companies']);
    Route::post('/companies', [SuperadminController::class, 'storeCompany']);
    Route::put('/companies/{company}', [SuperadminController::class, 'updateCompany']);
    Route::get('/companies/{company}/inspection', [SuperadminController::class, 'companyInspection']);
    Route::post('/companies/{company}/check', [SuperadminController::class, 'markCompanyChecked']);
    Route::get('/companies/{company}/chat', [SuperadminController::class, 'companyChat']);
    Route::post('/companies/{company}/chat', [SuperadminController::class, 'sendCompanyChatMessage']);

    // Faqat asosiy BOSS — o‘z sozlamalari + yordamchi adminlar
    Route::middleware('root_superadmin')->group(function () {
        Route::get('/settings', [SuperadminController::class, 'settings']);
        Route::put('/settings', [SuperadminController::class, 'updateSettings']);
        Route::get('/platform-admins', [SuperadminController::class, 'platformAdmins']);
        Route::post('/platform-admins', [SuperadminController::class, 'storePlatformAdmin']);
        Route::put('/platform-admins/{user}', [SuperadminController::class, 'updatePlatformAdmin']);
        Route::delete('/platform-admins/{user}', [SuperadminController::class, 'destroyPlatformAdmin']);
    });
});


Route::get('/support', [SupportController::class, 'info']);
Route::post('/support/messages', [SupportController::class, 'store']);

Route::get('/catalog/products', [CatalogProductController::class, 'publicIndex']);

Route::middleware(['auth:sanctum', 'owner'])->prefix('owner')->group(function () {
    Route::get('/dashboard', [OwnerCabinetController::class, 'dashboard']);
    Route::get('/regions', [OwnerCabinetController::class, 'regions']);

    Route::get('/directors', [OwnerCabinetController::class, 'directors']);
    Route::post('/directors', [OwnerCabinetController::class, 'storeDirector']);
    Route::put('/directors/{director}', [OwnerCabinetController::class, 'updateDirector']);

    Route::get('/farms', [OwnerCabinetController::class, 'farms']);
    Route::post('/farms', [OwnerCabinetController::class, 'storeFarm']);

    Route::get('/workers', [OwnerCabinetController::class, 'workers']);
    Route::post('/workers', [OwnerCabinetController::class, 'storeWorker']);
    Route::put('/workers/{worker}', [OwnerCabinetController::class, 'updateWorker']);

    Route::get('/ponds', [OwnerCabinetController::class, 'ponds']);
    Route::post('/ponds', [OwnerCabinetController::class, 'storePond']);
    Route::put('/ponds/{pond}', [OwnerCabinetController::class, 'updatePond']);
    Route::delete('/ponds/{pond}', [OwnerCabinetController::class, 'destroyPond']);

    Route::get('/requests-summary', [OwnerCabinetController::class, 'requestsSummary']);
    Route::get('/closure-requests', [OwnerCabinetController::class, 'closureRequests']);
    Route::post('/closure-requests/{closureRequest}/approve', [OwnerCabinetController::class, 'approveClosureRequest']);
    Route::post('/closure-requests/{closureRequest}/reject', [OwnerCabinetController::class, 'rejectClosureRequest']);

    Route::get('/pond-create-requests', [OwnerCabinetController::class, 'pondCreateRequests']);
    Route::post('/pond-create-requests/{createRequest}/approve', [OwnerCabinetController::class, 'approvePondCreateRequest']);
    Route::post('/pond-create-requests/{createRequest}/reject', [OwnerCabinetController::class, 'rejectPondCreateRequest']);

    Route::get('/support-messages', [OwnerCabinetController::class, 'supportMessages']);
    Route::post('/support-messages/{supportMessage}/read', [OwnerCabinetController::class, 'markSupportMessageRead']);

    // Faqat asosiy BOSS — kundalik, Venger Carp, xavfsizlik
    Route::middleware('root_superadmin')->group(function () {
        Route::get('/security', [OwnerCabinetController::class, 'security']);
        Route::put('/security/owner', [OwnerCabinetController::class, 'updateOwnerCredentials']);

        Route::get('/venger', [OwnerCabinetController::class, 'vengerOverview']);
        Route::post('/venger/staff', [OwnerCabinetController::class, 'storeVengerStaff']);
        Route::put('/venger/staff/{vengerStaff}', [OwnerCabinetController::class, 'updateVengerStaff']);
        Route::delete('/venger/staff/{vengerStaff}', [OwnerCabinetController::class, 'destroyVengerStaff']);

        Route::get('/venger/clients', [VengerClientController::class, 'index']);
        Route::get('/venger/clients/{client}', [VengerClientController::class, 'show']);
        Route::get('/venger/ponds', [VengerClientController::class, 'listPonds']);
        Route::get('/venger/results/{result}', [VengerClientController::class, 'showResult']);

        Route::get('/venger/feed-plans', [VengerFeedPlanController::class, 'index']);
        Route::post('/venger/feed-plans', [VengerFeedPlanController::class, 'store']);
        Route::get('/venger/feed-plans/{plan}', [VengerFeedPlanController::class, 'show']);
        Route::put('/venger/feed-plans/{plan}', [VengerFeedPlanController::class, 'update']);
        Route::delete('/venger/feed-plans/{plan}', [VengerFeedPlanController::class, 'destroy']);
        Route::post('/venger/feed-plans/{plan}/rows', [VengerFeedPlanController::class, 'storeRow']);
        Route::put('/venger/feed-plans/{plan}/rows/{row}', [VengerFeedPlanController::class, 'updateRow']);
        Route::delete('/venger/feed-plans/{plan}/rows/{row}', [VengerFeedPlanController::class, 'destroyRow']);

        Route::get('/planner', [OwnerPlannerController::class, 'show']);
        Route::put('/planner', [OwnerPlannerController::class, 'update']);
        Route::get('/planner/yearly', [OwnerPlannerController::class, 'showYearly']);
        Route::put('/planner/yearly', [OwnerPlannerController::class, 'updateYearly']);
        Route::get('/planner/monthly', [OwnerPlannerController::class, 'showMonthly']);
        Route::put('/planner/monthly', [OwnerPlannerController::class, 'updateMonthly']);
        Route::get('/planner/daily', [OwnerPlannerController::class, 'showDaily']);
        Route::put('/planner/daily/extras', [OwnerPlannerController::class, 'updateDailyExtras']);
        Route::post('/planner/inbox', [OwnerPlannerController::class, 'storeInbox']);
        Route::post('/planner/inbox/log-completed', [OwnerPlannerController::class, 'logCompleted']);
        Route::post('/planner/inbox/{inboxTask}/complete', [OwnerPlannerController::class, 'completeInbox']);
        Route::delete('/planner/inbox/{inboxTask}', [OwnerPlannerController::class, 'destroyInbox']);

        Route::get('/experiences', [OwnerExperienceController::class, 'index']);
        Route::post('/experiences', [OwnerExperienceController::class, 'store']);
        Route::get('/experiences/{experience}', [OwnerExperienceController::class, 'show']);
        Route::put('/experiences/{experience}', [OwnerExperienceController::class, 'update']);
        Route::delete('/experiences/{experience}', [OwnerExperienceController::class, 'destroy']);

        Route::get('/projects', [OwnerProjectController::class, 'index']);
        Route::post('/projects', [OwnerProjectController::class, 'store']);
        Route::get('/projects/{project}', [OwnerProjectController::class, 'show']);
        Route::put('/projects/{project}', [OwnerProjectController::class, 'update']);
        Route::delete('/projects/{project}', [OwnerProjectController::class, 'destroy']);
    });
});

$registerCompanyOps = function () {
    Route::get('/permission-keys', [CompanyHrController::class, 'permissionKeys']);

    // Kontekst (farm_id sinxron, havza ro‘yxati) — tenancy bilan; bo‘lim ruxsatisiz.
    // Aks holda feed/ombor/moliya rollari farm_id ololmaydi.
    Route::get('/regions', [DirectorCabinetController::class, 'regions']);
    Route::get('/farms', [DirectorCabinetController::class, 'farms']);
    Route::get('/farms/{farm}', [DirectorCabinetController::class, 'showFarm']);
    Route::get('/farms/{farm}/ponds', [DirectorCabinetController::class, 'farmPonds']);
    Route::get('/ponds', [DirectorCabinetController::class, 'ponds']);
    Route::get('/ponds/{pond}', [DirectorCabinetController::class, 'showPond']);
    Route::get('/water-quality-alerts', [DirectorCabinetController::class, 'waterQualityAlerts']);
    Route::get('/work-reminders', [DirectorCabinetController::class, 'workReminders']);
    Route::get('/closure-notifications', [DirectorCabinetController::class, 'closureNotifications']);
    Route::get('/boss-messages', [DirectorCabinetController::class, 'bossMessages']);
    Route::post('/boss-messages/read', [DirectorCabinetController::class, 'markBossMessageRead']);
    Route::get('/boss-chat', [DirectorCabinetController::class, 'bossChat']);
    Route::post('/boss-chat', [DirectorCabinetController::class, 'sendBossChat']);

    // Profil / audit — faqat kompaniya admini (employees ADMIN_ONLY).
    Route::middleware('permission:employees')->group(function () {
        Route::get('/profile', [DirectorCabinetController::class, 'profile']);
        Route::put('/profile', [DirectorCabinetController::class, 'updateProfile']);
        Route::get('/audit-journal', [DirectorCabinetController::class, 'auditJournal']);
    });

    Route::middleware('permission:roles')->group(function () {
        Route::get('/roles', [CompanyHrController::class, 'roles']);
        Route::post('/roles/ensure-defaults', [CompanyHrController::class, 'ensureDefaults']);
        Route::post('/roles', [CompanyHrController::class, 'storeRole']);
        Route::put('/roles/{role}', [CompanyHrController::class, 'updateRole']);
        Route::delete('/roles/{role}', [CompanyHrController::class, 'destroyRole']);
    });

    Route::middleware('permission:employees')->group(function () {
        Route::get('/employees', [CompanyHrController::class, 'employees']);
        Route::post('/employees', [CompanyHrController::class, 'storeEmployee']);
        Route::put('/employees/{employee}', [CompanyHrController::class, 'updateEmployee']);
        Route::delete('/employees/{employee}', [CompanyHrController::class, 'destroyEmployee']);
        Route::post('/employees/{employee}/reset-password', [CompanyHrController::class, 'resetEmployeePassword']);
    });

    Route::middleware('permission:farms')->group(function () {
        Route::post('/farms', [DirectorCabinetController::class, 'storeFarm']);
        Route::get('/farms/{farm}/pond-create-requests', [DirectorCabinetController::class, 'farmPondCreateRequests']);
    });

    Route::middleware('permission:ponds')->group(function () {
        Route::post('/farms/{farm}/ponds', [DirectorCabinetController::class, 'storeFarmPond']);
        Route::post('/ponds', [DirectorCabinetController::class, 'storePond']);
    });

    Route::middleware('permission:warehouse')->group(function () {
        Route::get('/farms/{farm}/warehouse/intake-feed-names', [DirectorCabinetController::class, 'warehouseIntakeFeedNames']);
        Route::post('/farms/{farm}/warehouse/intake', [DirectorCabinetController::class, 'storeWarehouseIntake']);
        Route::get('/farms/{farm}/warehouse', [DirectorCabinetController::class, 'warehouse']);
        Route::post('/farms/{farm}/warehouse', [DirectorCabinetController::class, 'storeWarehouseItem']);
        Route::post('/farms/{farm}/warehouse/{warehouseItem}/issue', [DirectorCabinetController::class, 'issueWarehouseItem']);
        Route::delete('/farms/{farm}/warehouse/{warehouseItem}', [DirectorCabinetController::class, 'destroyWarehouseItem']);
    });

    Route::middleware('permission:finance,finance_reports')->group(function () {
        Route::get('/farms/{farm}/finance/overview', [DirectorFinanceController::class, 'overview']);
        Route::get('/farms/{farm}/finance/sections', [DirectorFinanceController::class, 'sections']);
        Route::get('/farms/{farm}/finance/expenses', [DirectorFinanceController::class, 'expenses']);
    });

    Route::middleware('permission:finance')->group(function () {
        Route::post('/farms/{farm}/finance/sections', [DirectorFinanceController::class, 'storeSection']);
        Route::delete('/farms/{farm}/finance/sections/{section}', [DirectorFinanceController::class, 'destroySection']);
        Route::post('/farms/{farm}/finance/expenses', [DirectorFinanceController::class, 'storeExpense']);
        Route::put('/farms/{farm}/finance/expenses/{expense}', [DirectorFinanceController::class, 'updateExpense']);
        Route::delete('/farms/{farm}/finance/expenses/{expense}', [DirectorFinanceController::class, 'destroyExpense']);
    });

    Route::middleware('permission:daily_reports')->group(function () {
        Route::get('/ponds/{pond}/daily-reports', [DirectorCabinetController::class, 'dailyReports']);
        Route::get('/ponds/{pond}/feed-types', [DirectorCabinetController::class, 'feedTypes']);
        Route::post('/ponds/{pond}/daily-reports', [DirectorCabinetController::class, 'storeDailyReport']);
        Route::put('/ponds/{pond}/daily-reports/{dailyReport}', [DirectorCabinetController::class, 'updateDailyReport']);
        Route::delete('/ponds/{pond}/daily-reports/{dailyReport}', [DirectorCabinetController::class, 'destroyDailyReport']);
    });

    Route::middleware('permission:main_reports')->group(function () {
        Route::get('/ponds/{pond}/main-reports/{mainReport}/water-quality', [DirectorCabinetController::class, 'waterQualityDetail']);
        Route::get('/ponds/{pond}/main-reports', [DirectorCabinetController::class, 'mainReports']);
        Route::post('/ponds/{pond}/main-reports', [DirectorCabinetController::class, 'storeMainReport']);
        Route::put('/ponds/{pond}/main-reports/{mainReport}', [DirectorCabinetController::class, 'updateMainReport']);
        Route::delete('/ponds/{pond}/main-reports/{mainReport}', [DirectorCabinetController::class, 'destroyMainReport']);
    });

    Route::middleware('permission:activities,pond_close')->group(function () {
        Route::get('/ponds/{pond}/summary', [DirectorCabinetController::class, 'summary']);
        Route::get('/ponds/{pond}/activities', [DirectorCabinetController::class, 'activities']);
        Route::get('/ponds/{pond}/closure-archive', [DirectorCabinetController::class, 'closureArchive']);
    });

    Route::middleware('permission:pond_close')->group(function () {
        Route::get('/ponds/{pond}/closure', [DirectorCabinetController::class, 'closurePreview']);
        Route::post('/ponds/{pond}/closure-request', [DirectorCabinetController::class, 'submitClosureRequest']);
        Route::post('/ponds/{pond}/close', [DirectorCabinetController::class, 'closePond']);
    });

    Route::middleware('permission:fish_ops')->group(function () {
        Route::get('/ponds/{pond}/fish-stock', [DirectorCabinetController::class, 'fishStock']);
        Route::get('/ponds/{pond}/fish-operations', [DirectorCabinetController::class, 'fishOperations']);
        Route::post('/ponds/{pond}/fish-operations', [DirectorCabinetController::class, 'storeFishOperation']);
        Route::put('/ponds/{pond}/fish-operations/{fishOperation}', [DirectorCabinetController::class, 'updateFishOperation']);
        Route::delete('/ponds/{pond}/fish-operations/{fishOperation}', [DirectorCabinetController::class, 'destroyFishOperation']);
    });

    // Kunlik/asosiy hisobot ombordan yem/dori oladi
    Route::middleware('permission:daily_reports,warehouse,main_reports,fish_ops')->group(function () {
        Route::get('/ponds/{pond}/warehouse/available', [DirectorCabinetController::class, 'warehouseAvailable']);
    });
};

Route::middleware(['auth:sanctum', 'company'])->prefix('company')->group($registerCompanyOps);
Route::middleware(['auth:sanctum', 'director'])->prefix('director')->group($registerCompanyOps);

Route::middleware(['auth:sanctum', 'worker'])->prefix('worker')->group(function () {
    Route::get('/me', [WorkerCabinetController::class, 'me']);

    // Ruxsatlar director API bilan bir xil — legacy /api/worker orqali cheklovni aylanib o‘tmasin
    Route::middleware('permission:daily_reports|warehouse|main_reports|fish_ops')->group(function () {
        Route::get('/ponds/{pond}/warehouse/available', [WorkerCabinetController::class, 'warehouseAvailable']);
        Route::get('/ponds/{pond}/fish-stock', [WorkerCabinetController::class, 'fishStock']);
    });

    Route::middleware('permission:daily_reports')->group(function () {
        Route::get('/ponds/{pond}/daily-reports', [WorkerCabinetController::class, 'dailyReports']);
        Route::get('/ponds/{pond}/feed-types', [WorkerCabinetController::class, 'feedTypes']);
        Route::post('/ponds/{pond}/daily-reports', [WorkerCabinetController::class, 'storeDailyReport']);
        Route::put('/ponds/{pond}/daily-reports/{dailyReport}', [WorkerCabinetController::class, 'updateDailyReport']);
        Route::delete('/ponds/{pond}/daily-reports/{dailyReport}', [WorkerCabinetController::class, 'destroyDailyReport']);
    });

    Route::middleware('permission:main_reports')->group(function () {
        Route::get('/ponds/{pond}/main-reports', [WorkerCabinetController::class, 'mainReports']);
        Route::post('/ponds/{pond}/main-reports', [WorkerCabinetController::class, 'storeMainReport']);
        Route::put('/ponds/{pond}/main-reports/{mainReport}', [WorkerCabinetController::class, 'updateMainReport']);
        Route::delete('/ponds/{pond}/main-reports/{mainReport}', [WorkerCabinetController::class, 'destroyMainReport']);
    });
});

Route::middleware(['auth:sanctum', 'venger'])->prefix('venger')->group(function () {
    Route::get('/dashboard', [VengerCabinetController::class, 'dashboard']);

    Route::get('/clients', [VengerClientController::class, 'index']);
    Route::post('/clients', [VengerClientController::class, 'store']);
    Route::get('/clients/{client}', [VengerClientController::class, 'show']);
    Route::put('/clients/{client}', [VengerClientController::class, 'update']);
    Route::delete('/clients/{client}', [VengerClientController::class, 'destroy']);
    Route::post('/clients/{client}/results', [VengerClientController::class, 'storeResult']);

    Route::get('/ponds', [VengerClientController::class, 'listPonds']);
    Route::get('/results/{result}', [VengerClientController::class, 'showResult']);
    Route::put('/results/{result}', [VengerClientController::class, 'updateResult']);
    Route::delete('/results/{result}', [VengerClientController::class, 'destroyResult']);

    Route::get('/feed-plans', [VengerFeedPlanController::class, 'index']);
    Route::post('/feed-plans', [VengerFeedPlanController::class, 'store']);
    Route::get('/feed-plans/{plan}', [VengerFeedPlanController::class, 'show']);
    Route::put('/feed-plans/{plan}', [VengerFeedPlanController::class, 'update']);
    Route::delete('/feed-plans/{plan}', [VengerFeedPlanController::class, 'destroy']);
    Route::post('/feed-plans/{plan}/rows', [VengerFeedPlanController::class, 'storeRow']);
    Route::put('/feed-plans/{plan}/rows/{row}', [VengerFeedPlanController::class, 'updateRow']);
    Route::delete('/feed-plans/{plan}/rows/{row}', [VengerFeedPlanController::class, 'destroyRow']);
});

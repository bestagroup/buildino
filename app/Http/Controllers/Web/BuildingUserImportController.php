<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Services\Web\BuildingWorkbookExporter;
use App\Services\Web\BuildingWorkbookService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class BuildingUserImportController extends Controller
{
    private function authorizeBuilding(Request $request, Building $building): void
    {
        abort_unless(app(PermissionChecker::class)->allows($request->user(), 'users.create', $building), 403);
    }

    public function index(Request $request)
    {
        $buildings = Building::orderBy('title')->get()->filter(fn ($b) => app(PermissionChecker::class)->allows($request->user(), 'users.create', $b));
        abort_if($buildings->isEmpty(), 403);
        $building = $request->integer('building_id') ? $buildings->firstWhere('id', $request->integer('building_id')) : ($buildings->count() === 1 ? $buildings->first() : null);
        abort_if($request->integer('building_id') && ! $building, 403);

        return view('management.user-import', compact('buildings', 'building'));
    }

    public function template(Request $request, Building $building)
    {
        $this->authorizeBuilding($request, $building);
        $path = tempnam(sys_get_temp_dir(), 'buildino-import-');
        try {
            app(BuildingWorkbookExporter::class)->write($path, $building, $request->user());

            return response()->download($path, 'building-users.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        }
    }

    public function preview(Request $request, Building $building, BuildingWorkbookService $imports)
    {
        $this->authorizeBuilding($request, $building);
        $request->session()->forget('building_user_import');
        $request->validate(['file' => 'required|file|mimes:xlsx|max:2048']);
        try {
            $rows = $imports->validate($building, $request->user(), $imports->read($request->file('file')->getRealPath()))['rows'];
        } catch (ValidationException|HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['file' => 'فایل قابل خواندن نیست؛ از فایل نمونه XLSX استفاده کنید.']);
        }
        $token = (string) Str::uuid();
        $request->session()->put('building_user_import', ['format' => 4, 'token' => $token, 'user_id' => $request->user()->id, 'building_id' => $building->id, 'expires_at' => now()->addMinutes(20)->timestamp, 'rows' => $rows]);

        return view('management.user-import', ['buildings' => collect([$building]), 'building' => $building, 'rows' => $rows, 'token' => $token]);
    }

    public function commit(Request $request, Building $building, BuildingWorkbookService $imports)
    {
        $this->authorizeBuilding($request, $building);
        $preview = $request->session()->get('building_user_import');
        abort_unless($preview && ($preview['format'] ?? null) === 4 && $preview['user_id'] === $request->user()->id && $preview['building_id'] === $building->id && $preview['expires_at'] > time() && hash_equals($preview['token'], (string) $request->input('token')), 419);
        $imports->commit($building, $request->user(), $preview['rows']);
        $request->session()->forget('building_user_import');

        return redirect()->route('management.user-import.index', ['building_id' => $building->id])->with('status', 'اطلاعات جدید و اصلاحات فایل با موفقیت ثبت شد؛ ارتباط حساب‌ها با واحد حفظ شد. دعوت‌نامه را در بخش دعوت کاربران ارسال کنید.');
    }
}

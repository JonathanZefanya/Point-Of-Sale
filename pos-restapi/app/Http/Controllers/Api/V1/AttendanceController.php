<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ClockRequest;
use App\Http\Requests\Attendance\IndexAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Absensi karyawan (3.7). */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /** Absensi terbuka milik user yang login (null bila belum absen masuk). */
    public function current(Request $request): JsonResponse
    {
        $open = $this->attendance->current($request->user());

        return response()->json(['data' => $open ? new AttendanceResource($open) : null]);
    }

    public function clockIn(ClockRequest $request): JsonResponse
    {
        $open = $this->attendance->clockIn($request->user(), (int) $request->validated('outlet_id'), $request->validated('note'));

        return (new AttendanceResource($open))->response()->setStatusCode(201);
    }

    public function clockOut(ClockRequest $request): AttendanceResource
    {
        return new AttendanceResource($this->attendance->clockOut($request->user(), $request->validated('note')));
    }

    /** Rekap: daftar absensi + total menit per karyawan dalam periode, dibatasi outlet yang boleh diakses. */
    public function index(IndexAttendanceRequest $request): AnonymousResourceCollection
    {
        [$page, $totals] = $this->attendance->report($request->user(), $request->validated());

        return AttendanceResource::collection($page)->additional(['summary' => $totals]);
    }
}

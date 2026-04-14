<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CollegeController extends Controller
{
    private function orgUnitTable(): string
    {
        return Schema::hasTable('colleges') ? 'colleges' : 'departments';
    }

    private function orgUnitNameColumn(string $table): string
    {
        if (Schema::hasColumn($table, 'College_Name')) {
            return 'College_Name';
        }

        if (Schema::hasColumn($table, 'Department_Name')) {
            return 'Department_Name';
        }

        return 'College_Name';
    }

    public function index()
    {
        $table = $this->orgUnitTable();
        $nameColumn = $this->orgUnitNameColumn($table);
        $query = DB::table($table);
        if (Schema::hasColumn($table, 'created_at')) {
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('id');
        }
        $rows = $query->get();

        return response()->json([
            'data' => $rows->map(function ($row) use ($nameColumn) {
                $item = (array) $row;
                $item['College_Name'] = (string) ($item[$nameColumn] ?? '');
                return $item;
            })->values(),
        ]);
    }

    public function store(Request $request)
    {
        $table = $this->orgUnitTable();
        $nameColumn = $this->orgUnitNameColumn($table);

        $validated = $request->validate([
            'College_Name' => ['required', 'string', 'max:255', Rule::unique($table, $nameColumn)],
        ]);

        $insert = [$nameColumn => $validated['College_Name']];
        if (Schema::hasColumn($table, 'created_at')) {
            $insert['created_at'] = now();
        }
        if (Schema::hasColumn($table, 'updated_at')) {
            $insert['updated_at'] = now();
        }

        $id = DB::table($table)->insertGetId($insert);

        $row = DB::table($table)->where('id', $id)->first();
        $item = (array) $row;
        $item['College_Name'] = (string) ($item[$nameColumn] ?? '');
        return response()->json($item, 201);
    }

    public function update(Request $request, int $id)
    {
        $table = $this->orgUnitTable();
        $nameColumn = $this->orgUnitNameColumn($table);

        $validated = $request->validate([
            'College_Name' => ['required', 'string', 'max:255', Rule::unique($table, $nameColumn)->ignore($id)],
        ]);

        $update = [$nameColumn => $validated['College_Name']];
        if (Schema::hasColumn($table, 'updated_at')) {
            $update['updated_at'] = now();
        }

        DB::table($table)->where('id', $id)->update($update);

        $row = DB::table($table)->where('id', $id)->first();
        if (!$row) {
            return response()->json(['message' => 'Record not found.'], 404);
        }

        $item = (array) $row;
        $item['College_Name'] = (string) ($item[$nameColumn] ?? '');
        return response()->json($item);
    }

    public function destroy(int $id)
    {
        $table = $this->orgUnitTable();
        DB::table($table)->where('id', $id)->delete();
        return response()->json(null, 204);
    }
}

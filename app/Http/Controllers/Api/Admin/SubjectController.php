<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Http\Resources\SubjectResource;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index()
    {
        return SubjectResource::collection(Subject::latest()->get());
    }

    public function store(Request $request)
    {
        $request->validate(['Subject_Name' => 'required|unique:subjects,Subject_Name']);
        $subject = Subject::create($request->all());
        return new SubjectResource($subject);
    }

    public function update(Request $request, Subject $subject)
    {
        $request->validate(['Subject_Name' => 'required|unique:subjects,Subject_Name,' . $subject->id]);
        $subject->update($request->all());
        return new SubjectResource($subject);
    }

    public function destroy(Subject $subject)
    {
        $subject->delete();
        return response()->noContent();
    }
}

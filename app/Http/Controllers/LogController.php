<?php

namespace App\Http\Controllers;

use App\Models\Log;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public static function registrarLog($userId, $actionType, $tableName, $data, $errors = null)
    {
        Log::create([
            'user_id' => $userId,
            'action_type' => $actionType,
            'table_name' => $tableName,
            'data' => json_encode($data),
            'errors' => $errors ? json_encode($errors) : null,
        ]);
    }

    public function index(Request $request)
    {
        // Filtros de búsqueda
        $logs = Log::query();

        if ($request->has('user_id')) {
            $logs->where('user_id', $request->user_id);
        }

        if ($request->has('action_type')) {
            $logs->where('action_type', $request->action_type);
        }

        if ($request->has('table_name')) {
            $logs->where('table_name', $request->table_name);
        }

        if ($request->has('created_at')) {
            $logs->whereDate('created_at', $request->created_at);
        }

        return view('logs.index', ['logs' => $logs->paginate(15)]);
    }
}


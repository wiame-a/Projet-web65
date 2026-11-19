<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    public function __construct(private readonly ActivityService $activities)
    {
    }

    /**
     * Journal global : réservé aux administrateurs et aux archivistes.
     * Filtres : action, user_id, q (texte), date_debut, date_fin.
     */
    public function index(Request $request)
    {
        abort_unless($request->user()->isStaff(), 403, 'Le journal d\'activité est réservé aux administrateurs et aux archivistes.');

        $filters = $request->validate([
            'action' => ['nullable', Rule::in(ActivityLog::ACTIONS)],
            'user_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ], [
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'date_debut.date' => 'La date de début n\'est pas valide.',
            'date_fin.date' => 'La date de fin n\'est pas valide.',
        ]);

        return ActivityLogResource::collection($this->activities->search($filters));
    }

    /** Historique d'un document : propriétaire, archivistes et administrateurs. */
    public function forDocument(Document $document)
    {
        Gate::authorize('activity', $document);

        return ActivityLogResource::collection($this->activities->forDocument($document));
    }
}

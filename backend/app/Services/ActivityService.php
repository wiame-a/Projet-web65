<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/** Lecture du journal d'activité (l'écriture passe par ActivityLogger). */
class ActivityService
{
    /** Journal global filtré : action, user_id, q (texte), date_debut, date_fin. */
    public function search(array $f): LengthAwarePaginator
    {
        return ActivityLog::query()
            ->with(['user:id,name', 'document:id,code,titre'])
            ->when($f['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('description', 'like', '%'.$v.'%'))
            ->when($f['date_debut'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['date_fin'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($f['per_page'] ?? 20)
            ->withQueryString();
    }

    /** Les 50 dernières actions sur un document. */
    public function forDocument(Document $document): Collection
    {
        return $document->activities()
            ->with(['user:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }
}

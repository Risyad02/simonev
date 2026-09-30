<?php

namespace App\Services\Realization\Workflow;

/**
 * Aturan aktor pada sebuah transisi. Dimensi unit-scope (TBD-4) SENGAJA tidak
 * ada di sini — ia ditambahkan di lapisan otorisasi tanpa mengubah registry.
 */
enum ActorRule: string
{
    /** Pemilik data (input_by) atau aktor backup-only. */
    case OwnerOrBackup = 'owner_or_backup';

    /** Aktor tidak boleh sama dengan pemilik data (segregation of duties). */
    case NotOwner = 'not_owner';
}
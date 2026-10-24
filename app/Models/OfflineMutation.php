<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Records offline changes that have already been synchronized so retries are not applied twice.
 *
 * @property int $id
 * @property int $user_id
 * @property string $client_mutation_id
 * @property string $type
 * @property string $result
 */
#[Fillable(['user_id', 'client_mutation_id', 'type', 'result'])]
class OfflineMutation extends Model
{
    //
}

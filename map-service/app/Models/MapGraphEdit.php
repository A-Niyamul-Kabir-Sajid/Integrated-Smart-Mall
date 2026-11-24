<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change an administrator made to a map's navigation graph (ISM-114).
 *
 * - move_node:   node_code, data {x, y}
 * - remove_node: node_code
 * - connect:     from_code, to_code, data {step_free, one_way}
 * - edit_edge:   from_code, to_code, data {step_free?, direction?: both|forward|backward}
 * - remove_edge: from_code, to_code
 *
 * @property int $id
 * @property string $mall_map_id
 * @property string $key
 * @property int $sequence
 * @property string $action
 * @property string|null $node_code
 * @property string|null $from_code
 * @property string|null $to_code
 * @property array<string, mixed>|null $data
 * @property string|null $problem
 */
class MapGraphEdit extends Model
{
    public const ACTIONS = ['move_node', 'remove_node', 'connect', 'edit_edge', 'remove_edge'];

    protected $fillable = ['mall_map_id', 'key', 'sequence', 'action', 'node_code', 'from_code', 'to_code', 'data', 'problem'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['data' => 'array', 'sequence' => 'integer'];
    }

    /**
     * What the change does, in words.
     */
    public function describe(): string
    {
        $data = $this->data ?? [];

        return match ($this->action) {
            'move_node' => sprintf('Move node %s to x %.2f, y %.2f', $this->node_code, $data['x'] ?? 0, $data['y'] ?? 0),
            'remove_node' => "Remove node {$this->node_code}",
            'connect' => "Connect {$this->from_code} and {$this->to_code}".(! empty($data['one_way']) ? ' (one way)' : '').(($data['step_free'] ?? true) ? '' : ' (not step-free)'),
            'edit_edge' => "Change the walkway {$this->from_code} – {$this->to_code}: ".implode(', ', array_filter([
                isset($data['step_free']) ? ($data['step_free'] ? 'step-free' : 'not step-free') : null,
                match ($data['direction'] ?? null) {
                    'both' => 'both ways',
                    'forward' => "one way {$this->from_code} → {$this->to_code}",
                    'backward' => "one way {$this->to_code} → {$this->from_code}",
                    default => null,
                },
            ])),
            'remove_edge' => "Remove the walkway {$this->from_code} – {$this->to_code}",
            default => $this->action,
        };
    }

    /**
     * @return BelongsTo<MallMap, $this>
     */
    public function mallMap(): BelongsTo
    {
        return $this->belongsTo(MallMap::class);
    }
}

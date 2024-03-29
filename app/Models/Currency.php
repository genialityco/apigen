<?php

namespace App\Models;
use Moloquent;
    /*
      Attendize.com   - Event Management & Ticketing
     */

/**
 * Description of Currency.
 *
 * @author Dave
 */
class Currency extends MyBaseModel
{
    /**
     * Indicates whether the model should be timestamped.
     *
     * @var bool $timestamps
     */
    public $timestamps = false;
    /**
     * The database table used by the model.
     *
     * @var string $table
     */
    protected $table = 'currencies';
    /**
     * Indicates whether the model should use soft deletes.
     *
     * @var bool $softDelete
     */
    protected $softDelete = false;

    /**
     * The event associated with the currency.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function event()
    {
        return $this->belongsTo(\App\Models\Event::class);
    }
}

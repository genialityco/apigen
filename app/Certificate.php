<?php

namespace App;

//use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
//Importante usar moloquent!!!!!!
use Moloquent;

/**
 * Category Model
 *
 */ 
class Certificate extends Moloquent
{

    //protected $with = ['event'];

    //protected $table = 'category';
    /**
     * Category is owned by an event
     * @return void
     */
    public function event()
    {
        return $this->belongsTo('App\Event');
    }
    public function rol($rol_id)
    {
        return $this->belongsTo('App\RoleAttendee','foreign_key');

    }

    protected $fillable = [
        'name' , 'content' , 'background' , 'event_id' , 'rol_id'
    ];
}

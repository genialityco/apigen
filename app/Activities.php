<?php

namespace App;

//use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
//Importante usar moloquent!!!!!!
use Moloquent;
use Carbon\Carbon;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

/**
 * Category Model
 *
 */ 
class Activities extends Moloquent
{
    protected $with = ['activity_categories','space','hosts','type'];


    public function event()
    {
        return $this->belongsTo('App\Event');
    }

    public function space()
    {
        return $this->belongsTo('App\Space');
    }

    public function activity_categories()
    {
        return $this->belongsToMany('App\ActivityCategories' );
    }

    public function hosts()
    {
        return $this->belongsToMany('App\Host');
    }
    
    public function type()
    {
        return $this->belongsTo('App\Type');
    }
    public function quantity()
    {
        return $this->embedsMany('App\Quantity');   
    }
    public function users()
    {
        return $this->embedsMany('App\ActivityUsers');
    }
    protected $dateformat = 'Y-m-d H:i';
    protected $fillable = [
        'name' , 
        'datetime_start' , 
        "datetime_end" , 
        "space_id" ,
        "activity_categories_ids" , 
        "host_ids" , 
        "type_id" , 
        "description" ,
        "image" ,
        "quantity",
        "user" , 
        "event_id",
        "acitivity_users",
        "capacity",
        "remaining_capacity"
    ];
}
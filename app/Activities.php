<?php

namespace App;

////Importante usar moloquent!!!!!!
use Moloquent;
//use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Category Model
 *
 */
class Activities extends Moloquent
{
    //use SoftDeletes;

    protected $with = ['activity_categories', 'space', 'hosts', 'type', 'access_restriction_roles'];
    protected $appends = ['access_restriction_types_available'];

    /** Overriding  */
    public function toJson($options = 0)
    {
        $options = $options | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        return parent::toJson($options);
    }

/*
 * magic property to return type of restrictions activities
 */
    public function getAccessRestrictionTypesAvailableAttribute()
    {

        return config('app.activity_access_restriction_types');
    }

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
        return $this->belongsToMany('App\ActivityCategories');
    }

    public function hosts()
    {
        return $this->belongsToMany('App\Host');
    }

    public function type()
    {
        return $this->belongsTo('App\Type');
    }

    public function survey()
    {
        return $this->belongsTo('App\Survey');
    }

    public function access_restriction_roles()
    {
        return $this->belongsToMany('App\RoleAttendee');
    }

    public function users()
    {
        return $this->embedsMany('App\ActivityUsers');
    }

    protected $dateformat = 'Y-m-d H:i';
    protected $fillable = [
        'name',
        'subtitle',
        'datetime_start',
        "datetime_end",
        "space_id",
        "activity_categories_ids",
        "host_ids",
        "type_id",
        "description",
        "image",
        "user",
        "event_id",
        "selected_document",
        "acitivity_users",
        "capacity",
        "start_url",
        "join_url",
        "meeting_id",
        "meeting_video",
        "duplicate",
        "locale",
        "survey_ids",
        "locale_original",
        "remaining_capacity",
        "access_restriction_type",
        "access_restriction_rol_ids",
        "has_date",
        "zoom_meeting_video",
        "zoom_host_id",
        "zoom_host_name",
        "video",
        "bigmaker_meeting_id",
        "vimeo_id",
        "registration_message",
        "related_meetings",
        "platform",
        "date_start_zoom",
        "duration",
        "date_end_zoom",
        "start_date",
        "requires_registration",
        "password_meeting",     
        "latitude",
        "length" , 
        "activity_type",
	"discount",
	"userTypes" // Array: Tipos de usuario en la activididad
    ];
}

<?php

namespace App;

//use Illuminate\Database\Eloquent\Model;
use Moloquent;

class OrganizationUser extends Moloquent
{
    protected $fillable = [ 'userid', 'organization_id'];
    protected $with = ['user', 'organization'];
    
    public function user()
    {
        return $this->belongsTo('App\User', 'userid');
    }
    public function organization()
    {
        return $this->belongsTo('App\Organization','organization_id');
    }
/*     public function rol()
    {
        return $this->belongsTo('App\Rol','rol_id');
    } */
}

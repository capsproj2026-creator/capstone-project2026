<?php

namespace App\Models;

class GeneralInformation extends MongoModel
{
    protected $collection = 'general_informations';

    public $timestamps = false;

    protected $fillable = ['description'];
}

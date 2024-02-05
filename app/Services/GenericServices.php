<?php

namespace App\Services;

class GenericServices
{
    public function store($model, array $data)
    {
        return $model::create($data);
    }

    public function update($modelInstance, array $data)
    {
        $modelInstance->update($data);

        return $modelInstance;
    }
}

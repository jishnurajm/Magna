<?php

declare(strict_types=1);

namespace Magna\Content;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SchemaValidator
{
    /**
     * Validate entry data against a content type's schema rules.
     *
     * When $partial is true (for updates), only fields present in $data are
     * validated; absent fields are skipped regardless of their required flag.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(ContentType $type, array $data, bool $partial = false): array
    {
        $rules = [];

        foreach ($type->fields as $field) {
            if ($partial && ! array_key_exists($field->handle, $data)) {
                continue;
            }

            $fieldRules = $field->type->validationRules();

            if ($field->required && ! $partial) {
                array_unshift($fieldRules, 'required');
            } else {
                array_unshift($fieldRules, 'nullable');
            }

            $rules[$field->handle] = $fieldRules;
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}

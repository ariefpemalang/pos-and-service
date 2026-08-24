<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait HandlesEditableEntityCode
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function entityCodeRules(string $table, int $maxLength = 50, ?int $ignoreId = null, bool $required = false): array
    {
        $unique = Rule::unique($table, 'code');
        if ($ignoreId !== null) {
            $unique->ignore($ignoreId);
        }

        return [
            'code' => array_values(array_filter([
                $required ? 'required' : 'nullable',
                'string',
                'max:'.$maxLength,
                $unique,
            ])),
        ];
    }

    /**
     * @param  class-string  $modelClass
     */
    protected function resolveEntityCode(Request $request, string $modelClass, ?string $validatedCode = null): string
    {
        $code = trim((string) ($validatedCode ?? $request->input('code', '')));

        if ($code !== '') {
            return $code;
        }

        return $modelClass::generateCode();
    }
}

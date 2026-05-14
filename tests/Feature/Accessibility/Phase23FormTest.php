<?php

declare(strict_types=1);

namespace Tests\Feature\Accessibility;

use Tests\TestCase;

/**
 * Phase-23 A11Y-FORM-1: form error association watchdog (WCAG 1.3.1,
 * 3.3.1, 4.1.2). Pins that TextInput emits aria-invalid/aria-describedby
 * when errored, InputError renders a targetable id, and the Auth forms
 * actually wire the two together.
 */
class Phase23FormTest extends TestCase
{
    public function test_errored_input_is_aria_invalid_and_described_by(): void
    {
        $textInput = file_get_contents(resource_path('js/Components/TextInput.vue'));

        $this->assertStringContainsString(
            'errorMessage',
            $textInput,
            'A11Y-FORM-1: TextInput must accept an errorMessage prop.',
        );
        $this->assertStringContainsString(
            ':aria-invalid="ariaInvalid"',
            $textInput,
            'A11Y-FORM-1: TextInput must bind aria-invalid when errored.',
        );
        $this->assertStringContainsString(
            ':aria-describedby="ariaDescribedby"',
            $textInput,
            'A11Y-FORM-1: TextInput must bind aria-describedby to the error element when errored.',
        );

        $inputError = file_get_contents(resource_path('js/Components/InputError.vue'));
        $this->assertStringContainsString(
            ':id="id"',
            $inputError,
            'A11Y-FORM-1: InputError must render a prop-driven id so an input can describe-by it.',
        );
    }

    public function test_auth_forms_wire_error_association(): void
    {
        $forms = [
            'Login' => ['email', 'password'],
            'Register' => ['name', 'email', 'password', 'password_confirmation'],
            'ForgotPassword' => ['email'],
            'ResetPassword' => ['email', 'password', 'password_confirmation'],
            'ConfirmPassword' => ['password'],
        ];

        foreach ($forms as $form => $fields) {
            $contents = file_get_contents(resource_path("js/Pages/Auth/{$form}.vue"));

            foreach ($fields as $field) {
                $this->assertStringContainsString(
                    ":error-message=\"form.errors.{$field}\"",
                    $contents,
                    "A11Y-FORM-1: {$form}.vue field '{$field}' must pass :error-message to TextInput.",
                );
                $this->assertStringContainsString(
                    "id=\"{$field}-error\"",
                    $contents,
                    "A11Y-FORM-1: {$form}.vue field '{$field}' must give its InputError a stable id=\"{$field}-error\".",
                );
            }
        }
    }
}

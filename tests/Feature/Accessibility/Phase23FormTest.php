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

    public function test_required_label_has_visible_and_sr_marker(): void
    {
        $inputLabel = file_get_contents(resource_path('js/Components/InputLabel.vue'));

        $this->assertStringContainsString(
            'required',
            $inputLabel,
            'A11Y-FORM-2: InputLabel must accept a `required` prop.',
        );
        $this->assertStringContainsString(
            '<span class="text-red-600" aria-hidden="true">*</span>',
            $inputLabel,
            'A11Y-FORM-2: InputLabel must render a visible (aria-hidden) asterisk marker.',
        );
        $this->assertStringContainsString(
            '<span class="sr-only"> (required)</span>',
            $inputLabel,
            'A11Y-FORM-2: InputLabel must render sr-only " (required)" text so the cue is not symbol-only.',
        );

        // Every InputLabel in the Auth forms is for a required field —
        // each must pass the `required` prop.
        foreach (['Login', 'Register', 'ForgotPassword', 'ResetPassword', 'ConfirmPassword'] as $form) {
            $contents = file_get_contents(resource_path("js/Pages/Auth/{$form}.vue"));
            $this->assertSame(
                substr_count($contents, '<InputLabel'),
                substr_count($contents, '<InputLabel required'),
                "A11Y-FORM-2: every InputLabel in {$form}.vue must pass `required` (all its fields are mandatory).",
            );
        }
    }

    public function test_helper_text_is_associated(): void
    {
        $register = file_get_contents(resource_path('js/Pages/Auth/Register.vue'));

        $this->assertStringContainsString(
            'aria-describedby="role-helper"',
            $register,
            'A11Y-FORM-3: the role <select> must point at its helper text via aria-describedby.',
        );
        $this->assertStringContainsString(
            'id="role-helper"',
            $register,
            'A11Y-FORM-3: the role helper-text element must carry the matching id="role-helper".',
        );
    }
}

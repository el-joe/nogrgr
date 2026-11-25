<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Well-known audit actions. Free-form actions ("http.admin.products.update",
 * "catalog.product.updated") are allowed too; use these for cross-cutting ones.
 */
enum AuditAction: string
{
    use HasEnumHelpers;

    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case Login = 'auth.login';
    case Logout = 'auth.logout';
    case LoginFailed = 'auth.login_failed';
    case Impersonated = 'auth.impersonated';
    case PasswordResetRequested = 'auth.password_reset_requested';
    case PasswordReset = 'auth.password_reset';
    case PasswordChanged = 'auth.password_changed';
    case Registered = 'auth.registered';
    case AccountDeleted = 'auth.account_deleted';
    case HttpRequest = 'http.request';
}

<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/requests-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=requests-for-laravel">
    <img src="https://raw.githubusercontent.com/roundly-consulting/requests-for-laravel/main/art/hero.png" alt="Requests for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/requests-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/requests-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/requests-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/requests-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/requests-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/requests-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=requests-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Requests for Laravel

Requests that need sign-off — claims, applications, expense or leave requests — submitted by any
Eloquent model and decided by the approvers you name. Approval rules, multi-stage pipelines,
delegation, expiry and a full audit trail run on `approvals-for-laravel`, while each request keeps
its own status lifecycle and events.

## Installation

Requires PHP 8.4, Laravel 12 or 13.

```bash
composer require roundly-consulting/requests-for-laravel
php artisan vendor:publish --tag="requests-migrations"
php artisan vendor:publish --tag="approvals-migrations"
php artisan migrate
```

If the models that author requests have UUID/ULID keys, set `REQUESTS_KEY_TYPE` **before**
migrating.

## Usage

Let the models that author and approve requests use the two traits:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Approvals\Traits\GivesApprovals;
use RoundlyConsulting\Requests\Concerns\HasRequests;
use RoundlyConsulting\Requests\Contracts\HasRequests as HasRequestsContract;

class User extends Model implements GivesApprovalsInterface, HasRequestsContract
{
    use GivesApprovals;
    use HasRequests;
}
```

Submit a request that needs sign-off from named approvers:

```php
use RoundlyConsulting\Requests\Facades\Requests;

$request = Requests::make()
    ->author($user)
    ->type('Claim')
    ->title('Expense reimbursement')
    ->requireApprovalsFrom([$alice, $bob])   // only they, or their delegates, may decide
    ->expiresAt(now()->addDays(7))
    ->create();
```

Then decide it — by default every approver must approve:

```php
Requests::approve($request, $alice);                       // 1 of 2 — still New
Requests::approve($request, $bob, reason: 'Looks good');   // 2 of 2 — Approved

$request->status;                    // Status::Approved
$user->approvedRequests()->get();    // the author's approved requests
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/requests-for-laravel](https://roundly-consulting.com/open-source/docs/requests-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=requests-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=requests-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=requests-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).

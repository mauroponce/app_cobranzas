<?php

use App\Domain\Tenancy\InvitationService;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$data = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);

try {
    app(InvitationService::class)->accept($data['token'], User::findOrFail($data['user_id']));
    echo 'accepted';
} catch (HttpException $exception) {
    echo $exception->getStatusCode();
} catch (Throwable $exception) {
    echo get_class($exception);
}

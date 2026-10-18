<?php

use App\Domain\Tenancy\InvitationService;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$data = json_decode((string) fgets(STDIN), true, 512, JSON_THROW_ON_ERROR);

echo "ready\n";
flush();

if (trim((string) fgets(STDIN)) !== 'accept') {
    exit(2);
}

try {
    app(InvitationService::class)->accept($data['token'], User::findOrFail($data['user_id']));
    echo "accepted\n";
} catch (HttpException $exception) {
    echo $exception->getStatusCode()."\n";
} catch (Throwable $exception) {
    echo get_class($exception)."\n";
}

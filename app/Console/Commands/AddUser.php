<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class AddUser extends Command
{
    protected $signature = 'cronmon:add-user
        {username? : SSO username, e.g. kmc2y}
        {email? : Email address}
        {surname? : Surname}
        {forenames? : Forenames}
        {--admin : Make this user an admin}';

    protected $description = 'Add a Cronmon user (e.g. to bootstrap the app on a fresh deploy).';

    public function handle(): int
    {
        $interactive = ! filled($this->argument('username'));

        if ($interactive) {
            [$username, $email, $surname, $forenames, $isAdmin] = $this->promptForDetails();
        } else {
            $username = $this->argument('username');
            $email = $this->argument('email');
            $surname = $this->argument('surname');
            $forenames = $this->argument('forenames');
            $isAdmin = (bool) $this->option('admin');
        }

        $data = [
            'username' => $username,
            'email' => $email,
            'surname' => $surname,
            'forenames' => $forenames,
        ];

        $validator = Validator::make($data, [
            'username' => ['required', 'string', 'regex:/^[a-z]+[0-9]+[a-z]$/', Rule::unique('users', 'username')],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'surname' => ['required', 'string', 'max:255'],
            'forenames' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            ...$data,
            'is_staff' => true,
            'is_admin' => $isAdmin,
            'password' => bcrypt(Str::random(64)),
        ]);

        $this->info("Created {$user->email} ({$user->username})".($user->is_admin ? ' as an admin.' : '.'));

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: bool}
     */
    private function promptForDetails(): array
    {
        $email = text(label: 'Email', required: true);
        [$forenamesGuess, $surnameGuess] = $this->guessNamesFromEmail($email);

        $username = text(label: 'SSO username', placeholder: 'e.g. kmc2y', required: true);
        $forenames = text(label: 'Forenames', default: $forenamesGuess, required: true);
        $surname = text(label: 'Surname', default: $surnameGuess, required: true);
        $isAdmin = confirm(label: 'Make this user an admin?', default: true);

        return [$username, $email, $surname, $forenames, $isAdmin];
    }

    /**
     * @return array{0: string, 1: string} [forenames, surname]
     */
    private function guessNamesFromEmail(string $email): array
    {
        $local = Str::before($email, '@');
        $parts = explode('.', $local);

        if (is_numeric(end($parts))) {
            array_pop($parts);
        }

        if (count($parts) < 2) {
            return ['', ''];
        }

        return [Str::title($parts[0]), Str::title($parts[1])];
    }
}

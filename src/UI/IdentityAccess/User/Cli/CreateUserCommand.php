<?php

namespace App\UI\IdentityAccess\User\Cli;

use App\Logic\IdentityAccess\User\Dto\CreateUserRequest;
use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\UseCase\CreateUserUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:create', description: 'Legt einen CMS-Benutzer an.')]
class CreateUserCommand extends Command
{
    public function __construct(private readonly CreateUserUseCase $useCase)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('display-name', InputArgument::REQUIRED)
            ->addOption('role', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Rolle', ['viewer'])
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Passwort; ohne Option wird interaktiv gefragt');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $displayName = $input->getArgument('display-name');
        $password = $input->getOption('password');
        $roleValues = $input->getOption('role');

        if (!is_string($email) || !is_string($displayName) || !is_array($roleValues)) {
            $io->error('Ungültige Eingabe.');

            return Command::INVALID;
        }

        if (!is_string($password) || $password === '') {
            $password = $io->askHidden('Passwort');
        }
        if (!is_string($password) || $password === '') {
            $io->error('Ein Passwort ist erforderlich.');

            return Command::INVALID;
        }

        try {
            $roles = array_map(
                static fn (string $role): Role => Role::from($role),
                array_values(array_filter($roleValues, is_string(...))),
            );
        } catch (\ValueError $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        $user = $this->useCase->execute(new CreateUserRequest($email, $displayName, $password, $roles));
        $io->success(sprintf('Benutzer %s wurde angelegt.', $user->email));

        return Command::SUCCESS;
    }
}

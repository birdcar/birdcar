<?php

namespace App\Console\Commands;

use App\Actions\Admin\AdminInvitationBrokerException;
use App\Actions\Admin\AdminInvitationDeliveryException;
use App\Actions\Admin\AdminInvitationException;
use App\Actions\Admin\InviteAdministrator;
use Illuminate\Console\Command;

class InviteAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:invite {email : Email address to invite} {--name= : Display name for a newly-created user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Invite a root Admin through the password reset broker';

    /**
     * Execute the console command.
     */
    public function handle(InviteAdministrator $inviteAdministrator): int
    {
        $email = $this->argument('email');
        $name = $this->option('name');

        try {
            $result = $inviteAdministrator->handle($email, $name);
        } catch (AdminInvitationDeliveryException $exception) {
            $this->error($exception->getMessage());
            $this->warn('The account and bootstrap roles may already be provisioned; retrying is safe after mail is corrected.');

            return Command::FAILURE;
        } catch (AdminInvitationBrokerException $exception) {
            $this->error($exception->getMessage());
            $this->warn('The account and bootstrap roles may already be provisioned; retrying is safe when the broker allows it.');

            return Command::FAILURE;
        } catch (AdminInvitationException $exception) {
            $this->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->info('Admin invitation accepted by the configured mail transport.');
        $this->line('Provisioned user ID: '.$result->user->getKey());
        $this->line('Assigned bootstrap roles: '.implode(', ', $result->roles));
        $this->warn('No password, reset token, or setup URL was printed.');

        return Command::SUCCESS;
    }
}

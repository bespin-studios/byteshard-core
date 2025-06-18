<?php

namespace byteShard\Internal\Authentication\Provider;

use byteShard\Authentication\DB;
use byteShard\Authentication\Enum\Action;
use byteShard\Authentication\User;
use byteShard\DataModelInterface;
use byteShard\Internal\Authentication\AuthenticationAction;
use byteShard\Internal\Authentication\AuthenticationInterface;
use byteShard\Internal\Authentication\AuthenticationResult;
use byteShard\Internal\Authentication\ProviderInterface;
use byteShard\Internal\Authentication\Struct\Result;
use byteShard\Internal\Login\Struct\Credentials;
use byteShard\Session;

class Local implements ProviderInterface
{
    private string $username;

    public function __construct(
        private readonly DataModelInterface       $dataModel,
        private readonly ?AuthenticationInterface $authenticationObject = null
    ) {
    }

    public function authenticate(?Credentials $credentials = null): bool
    {
        //TODO: GrantLogin check
        if ($credentials === null) {
            $invalidCredentialsAction = AuthenticationAction::INVALID_CREDENTIALS;
            $invalidCredentialsAction->processAction($this);
        }
        if (!$this->authenticationObject instanceof DB) {
            $result = $this->authenticateAgainstDefaultProvider($credentials);
        } else {
            $result = $this->authenticateAgainstLegacyAppProvider($credentials);
        }
        if ($result->isSuccess() === true) {
            $this->username = $credentials->getUsername();
            $user           = User::createUser(
                username: $credentials->getUsername(),
                firstname: '',
                lastname: '',
                mail: ''
            );
            $user->store();
            return true;
        }
        $action = $result->getAction();
        if ($action === null) {
            $action = AuthenticationAction::UNEXPECTED_ERROR;
        }
        $action->processAction($this);
    }

    private function authenticateAgainstDefaultProvider(Credentials $credentials): AuthenticationResult
    {
        //TODO: GrantLogin check
        $authenticationObject = new DB($this->dataModel);
        return $authenticationObject->authenticate($credentials->getUsername(), $credentials->getPassword());
    }

    private function authenticateAgainstLegacyAppProvider(Credentials $credentials): AuthenticationResult
    {
        $authenticationResult = new Result();
        $authenticationResult->setSuccess(false);
        $authenticationResult->username = $credentials->getUsername();
        $authenticationResult->password = $credentials->getPassword();
        $authenticationResult->domain   = $credentials->getDomain();

        $this->authenticationObject->authenticate($authenticationResult);

        $result = new AuthenticationResult($authenticationResult->success);
        if ($authenticationResult->action !== null) {
            switch ($authenticationResult->action) {
                case Action::OLD_PASSWORD_WRONG:
                case Action::INVALID_CREDENTIALS:
                    $result->setAction(AuthenticationAction::INVALID_CREDENTIALS);
                    break;
                case Action::CHANGE_PASSWORD:
                    $result->setAction(AuthenticationAction::CHANGE_PASSWORD);
                    break;
                case Action::DISPLAY_TOO_MANY_FAILED_ATTEMPS:
                    $result->setAction(AuthenticationAction::DISPLAY_TOO_MANY_FAILED_ATTEMPTS);
                    break;
                case Action::NEW_PASSWORD_REPEAT_FAILED:
                    $result->setAction(AuthenticationAction::NEW_PASSWORD_REPEAT_FAILED);
                    break;
                case Action::NEW_PASSWORD_USED_IN_PAST:
                    $result->setAction(AuthenticationAction::NEW_PASSWORD_USED_IN_PAST);
                    break;
                case Action::NEW_PASSWORD_DOESNT_MATCH_POLICY:
                    $result->setAction(AuthenticationAction::NEW_PASSWORD_DOESNT_MATCH_POLICY);
                    break;
                case Action::PASSWORD_EXPIRED:
                    $result->setAction(AuthenticationAction::PASSWORD_EXPIRED);
                    break;
                case Action::AUTHENTICATION_TARGET_UNREACHABLE:
                    $result->setAction(AuthenticationAction::AUTHENTICATION_TARGET_UNREACHABLE);
                    break;
                default:
                    $result->setAction(AuthenticationAction::UNEXPECTED_ERROR);
                    break;
            }
        }
        return $result;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function userHasValidAndNotExpiredSession(int $sessionTimeoutInMinutes): bool
    {
        $loginState = Session::getLoginState();
        if ($loginState === true && (($sessionTimeoutInMinutes * 60) < (time() - Session::getTimeOfLastUserRequest()))) {
            return false; // Session timeout -> logout
        }
        return $loginState;
    }

    public function logout(): void
    {
        User::logout();
    }
}
<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * The applications endpoints (api/v1/applications*): turns a request body
 * into an ApplicationsManager call and the result into an HTTP status and a
 * JSON payload. Invalid requests get 400, unknown applications 404.
 */
class ApplicationsApi
{
    public const TARGET_KEY = "key";

    public const TARGET_TOKEN = "token";

    /**
     * Manages the applications.
     *
     * @var ApplicationsManager
     */
    private ApplicationsManager $manager;


    /**
     * Creates the API.
     *
     * @param ApplicationsManager $manager Manages the applications.
     */
    public function __construct(ApplicationsManager $manager)
    {
        $this->manager = $manager;

    }//end __construct()


    /**
     * GET api/v1/applications: every application, tokens masked.
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function list(): array
    {
        return [
                200,
                ["applications" => $this->manager->list()],
               ];

    }//end list()


    /**
     * POST api/v1/applications {"name"}: creates an application; the payload
     * holds its full token.
     *
     * @param string $body The request body.
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function create(string $body): array
    {
        try {
            $input       = $this->getInput($body);
            $application = $this->manager->create($this->getName($input));
        } catch (InvalidApplicationException $exception) {
            return $this->getBadRequest($exception);
        }

        return [
                201,
                ["application" => $application],
               ];

    }//end create()


    /**
     * POST api/v1/applications/update {"id", "name"}: renames an application.
     *
     * @param string $body The request body.
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function update(string $body): array
    {
        try {
            $input       = $this->getInput($body);
            $application = $this->manager->rename($this->getId($input), $this->getName($input));
        } catch (InvalidApplicationException $exception) {
            return $this->getBadRequest($exception);
        }

        return $this->getFound($application);

    }//end update()


    /**
     * POST api/v1/applications/rotate {"id", "target": "key"|"token"}: rotates
     * the key (rebuilt from the current name) or the token (the payload holds
     * the full new token).
     *
     * @param string $body The request body.
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function rotate(string $body): array
    {
        try {
            $input       = $this->getInput($body);
            $appId       = $this->getId($input);
            $application = $this->rotateTarget($appId, ($input["target"] ?? ""));
        } catch (InvalidApplicationException $exception) {
            return $this->getBadRequest($exception);
        }

        return $this->getFound($application);

    }//end rotate()


    /**
     * Rotates the key or the token of the application.
     *
     * @param integer $appId  The application's ID.
     * @param mixed   $target The target, "key" or "token".
     *
     * @return array<string, mixed>|null
     *
     * @throws InvalidApplicationException When the target is neither.
     */
    private function rotateTarget(int $appId, $target): ?array
    {
        if ($target === self::TARGET_KEY) {
            return $this->manager->rotateKey($appId);
        }

        if ($target === self::TARGET_TOKEN) {
            return $this->manager->rotateToken($appId);
        }

        throw new InvalidApplicationException("The target must be \"key\" or \"token\"");

    }//end rotateTarget()


    /**
     * 400 with the error.
     *
     * @param InvalidApplicationException $exception Why the request is invalid.
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function getBadRequest(InvalidApplicationException $exception): array
    {
        return [
                400,
                ["error" => $exception->getMessage()],
               ];

    }//end getBadRequest()


    /**
     * 200 with the application, or 404 when there is none.
     *
     * @param array<string, mixed>|null $application The application.
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function getFound(?array $application): array
    {
        if ($application === null) {
            return [
                    404,
                    ["error" => "Application not found"],
                   ];
        }

        return [
                200,
                ["application" => $application],
               ];

    }//end getFound()


    /**
     * The request body, decoded.
     *
     * @param string $body The request body.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidApplicationException When the body is not a JSON object.
     */
    private function getInput(string $body): array
    {
        $input = json_decode($body, true);
        if (is_array($input) === false) {
            throw new InvalidApplicationException("Invalid JSON provided");
        }

        return $input;

    }//end getInput()


    /**
     * The application's name from the request ("" when there is none).
     *
     * @param array<string, mixed> $input The request body, decoded.
     *
     * @return string
     *
     * @throws InvalidApplicationException When the name is not a string.
     */
    private function getName(array $input): string
    {
        $name = ($input["name"] ?? "");
        if (is_string($name) === false) {
            throw new InvalidApplicationException("The application's name must be a string");
        }

        return $name;

    }//end getName()


    /**
     * The application's ID from the request.
     *
     * @param array<string, mixed> $input The request body, decoded.
     *
     * @return integer
     *
     * @throws InvalidApplicationException When there is no valid ID.
     */
    private function getId(array $input): int
    {
        $appId = filter_var(($input["id"] ?? 0), FILTER_VALIDATE_INT);
        if ($appId === false || $appId < 1) {
            throw new InvalidApplicationException("A valid application ID is required");
        }

        return $appId;

    }//end getId()


}//end class

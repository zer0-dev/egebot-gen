<?php

namespace App\Models;

class Message
{
    private string $type;
    private ?string $message, $callback_query_id, $file_link;
    private User $user;

    public function __construct(string $type, User $user, ?string $message, ?string $callback_query_id = null, ?string $file_link = null){
        $this->type = $type;
        $this->user = $user;
        $this->message = $message;
        $this->callback_query_id = $callback_query_id;
        $this->file_link = $file_link;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @return ?string
     */
    public function getCallbackQueryId(): ?string
    {
        return $this->callback_query_id;
    }

    /**
     * @return string|null
     */
    public function getFileLink(): ?string
    {
        return $this->file_link;
    }
}

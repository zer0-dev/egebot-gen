<?php

namespace App\Models;

class BotButton
{
    private string $text, $color;
    private ?string $callback_data, $url;
    private bool $required;

    public function __construct(string $text, string $color = 'secondary', array $callback_data = [], ?string $url = null, bool $required = true){
        $this->text = $text;
        $this->color = $color;
        $this->callback_data = json_encode($callback_data);
        $this->url = $url;
        $this->required = $required;
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @return string
     */
    public function getColor(): string
    {
        return $this->color;
    }

    /**
     * @return string
     */
    public function getCallbackData(): string
    {
        return $this->callback_data;
    }

    /**
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }
}

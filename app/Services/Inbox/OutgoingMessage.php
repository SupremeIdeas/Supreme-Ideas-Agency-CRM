<?php

namespace App\Services\Inbox;

/**
 * One thing a person wants to say in a conversation (MASTER-ONLY, Business Suite inbox): some text, and any of media files, a voice note, a location, a contact
 * card or a poll. Files are already stored (InboxMedia); this carries only their descriptors, so it can be saved as JSON for a scheduled send and rebuilt later.
 *
 * @phpstan-type FileDesc array{path: string, name: string, mime: string, size: int, kind: string}
 */
final class OutgoingMessage
{
    /**
     * @param  list<array{path: string, name: string, mime: string, size: int, kind: string}>  $files  kind: image | gif | video | audio | voice | document
     * @param  ?array{lat: float, lng: float, label: ?string}  $location
     * @param  ?array{name: string, phone: ?string}  $contact
     * @param  ?array{question: string, options: list<string>, multiple: bool}  $poll
     */
    public function __construct(
        public string $text = '',
        public array $files = [],
        public ?array $location = null,
        public ?array $contact = null,
        public ?array $poll = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return trim($this->text) === '' && $this->files === [] && ! $this->location && ! $this->contact && ! $this->poll;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['text' => $this->text, 'files' => $this->files, 'location' => $this->location, 'contact' => $this->contact, 'poll' => $this->poll];
    }

    /** @param array<string,mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self((string) ($a['text'] ?? ''), $a['files'] ?? [], $a['location'] ?? null, $a['contact'] ?? null, $a['poll'] ?? null);
    }
}

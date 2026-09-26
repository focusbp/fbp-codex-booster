<?php

/** App-owned API integration settings. Values are encoded for lossless FFM storage, not encrypted. */
final class ExternalKeys {
    private FFM $db;

    public function __construct(FFM $db) { $this->db = $db; }

    public function metadata(string $search = ''): array {
        $items = [];
        foreach ($this->db->getall('key', SORT_ASC) as $row) {
            if ($search !== '' && mb_stripos($row['key'], $search) === false && mb_stripos($row['title'], $search) === false) continue;
            $items[] = ['id' => $row['id'], 'key' => $row['key'], 'title' => $row['title']];
        }
        return $items;
    }

    public function find(int $id): ?array {
        foreach ($this->metadata() as $row) if ((int) $row['id'] === $id) return $row;
        return null;
    }

    public function get(string $key): ?string {
        $found = null;
        foreach ($this->db->getall() as $row) {
            if ($row['key'] !== $key) continue;
            if ($found !== null) throw new RuntimeException('Duplicate external integration key.');
            $found = base64_decode($row['value'], true);
            if ($found === false) throw new RuntimeException('Invalid external integration value.');
        }
        return $found;
    }

    public function validate(int $id, string $key, string $title, string $value): array {
        $errors = [];
        if ($key === '' || strlen($key) > 255 || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $key)) $errors['key'] = 'key_invalid';
        if (trim($title) === '' || strlen($title) > 255) $errors['title'] = 'title_invalid';
        if (strlen($value) > 8192 || ($id === 0 && $value === '')) $errors['external_key_secret'] = 'value_invalid';
        if ($id < 0 || ($id > 0 && $this->find($id) === null)) $errors['id'] = 'not_found';
        foreach ($this->metadata() as $row) {
            if ($row['key'] === $key && (int) $row['id'] !== $id) $errors['key'] = 'duplicate';
        }
        return $errors;
    }

    // The writable FFM holds its exclusive lock through validation and save.
    public function save(int $id, string $key, string $title, string $value): int {
        if ($this->validate($id, $key, $title, $value)) throw new InvalidArgumentException('Invalid external integration key settings.');
        $row = $id > 0 ? $this->db->get($id) : [];
        $row['key'] = $key;
        $row['title'] = trim($title);
        if ($id === 0 || $value !== '') $row['value'] = base64_encode($value);
        if ($id > 0) { $this->db->update($row); return $id; }
        return (int) $this->db->insert($row);
    }

    public function delete(int $id): void {
        if ($this->find($id) === null) throw new InvalidArgumentException('External integration key not found.');
        $this->db->delete($id);
    }
}

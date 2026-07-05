<?php
declare(strict_types=1);

final class Flash
{
    public static function set(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function get(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }

    public static function has(): bool
    {
        return !empty($_SESSION['_flash']);
    }

    public static function render(): string
    {
        $messages = self::get();
        if (empty($messages)) {
            return '';
        }

        $icons = [
            'success' => 'bi-check-circle-fill',
            'danger'  => 'bi-x-circle-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            'info'    => 'bi-info-circle-fill',
        ];

        $html = '<div id="flash-container">';
        foreach ($messages as $msg) {
            $icon = $icons[$msg['type']] ?? 'bi-info-circle-fill';
            $html .= '<div class="flash-card flash-' . $msg['type'] . '">
                <div class="flash-icon"><i class="bi ' . $icon . '"></i></div>
                <div class="flash-body">
                    <div class="flash-title">' . ucfirst($msg['type']) . '</div>
                    <div class="flash-message">' . htmlspecialchars($msg['message']) . '</div>
                </div>
                <button class="flash-close" onclick="dismissFlash(this)">&times;</button>
            </div>';
        }
        $html .= '</div>';
        return $html;
    }
}

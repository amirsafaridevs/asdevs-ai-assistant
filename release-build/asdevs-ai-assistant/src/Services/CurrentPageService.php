<?php

declare(strict_types=1);

namespace ASDevs\AIAssistant\Services;

class CurrentPageService
{
    /**
     * Detect current admin page info.
     */
    public function getCurrentPage(): array
    {
        if (!is_admin()) {
            return [
                'screenId'  => '',
                'pageTitle' => '',
                'pageUrl'   => '',
                'isAdmin'   => false,
            ];
        }

        $screen = get_current_screen();

        if (!$screen) {
            return [
                'screenId'  => '',
                'pageTitle' => '',
                'pageUrl'   => self_admin_url(),
                'isAdmin'   => true,
            ];
        }

        // Build the current URL
        global $pagenow;
        $pageUrl = self_admin_url($pagenow ?? 'admin.php');

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading URL parameters to detect current page, not form processing
        if (!empty($_GET['page'])) {
            $pageUrl = add_query_arg('page', sanitize_text_field(wp_unslash($_GET['page'])), $pageUrl);
        }
        if (!empty($_GET['post_type'])) {
            $pageUrl = add_query_arg('post_type', sanitize_text_field(wp_unslash($_GET['post_type'])), $pageUrl);
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        return [
            'screenId'  => $screen->id ?? '',
            'pageTitle' => $this->getAdminTitle(),
            'pageUrl'   => $pageUrl,
            'isAdmin'   => true,
        ];
    }

    /**
     * Get the admin page title.
     */
    private function getAdminTitle(): string
    {
        global $title;

        if (!empty($title)) {
            return wp_strip_all_tags($title);
        }

        $screen = get_current_screen();
        if ($screen && !empty($screen->label)) {
            return $screen->label;
        }

        return 'WordPress Admin';
    }
}

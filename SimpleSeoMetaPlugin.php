<?php
class SimpleSeoMetaPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'install',
        'uninstall',
        'upgrade',
        'config_form',
        'config',
        'initialize',
        'admin_head',
        'public_head',
        'admin_footer',
        'after_save_record',
        'after_save_simple_pages_page',
    );

    protected $_defaults = array(
        'simple_seo_meta_enable_title_rewrite' => '1',
        'simple_seo_meta_title_template' => '{page_title} | {site_title}',
        'simple_seo_meta_default_title' => '',
        'simple_seo_meta_default_description' => '',
        'simple_seo_meta_enable_canonical' => '1',
        'simple_seo_meta_strip_query_from_canonical' => '1',

        'simple_seo_meta_robots_base' => '',
        'simple_seo_meta_robots_custom' => '',
        'simple_seo_meta_robots_nosnippet' => '0',
        'simple_seo_meta_robots_indexifembedded' => '0',
        'simple_seo_meta_robots_notranslate' => '0',
        'simple_seo_meta_robots_noimageindex' => '0',
        'simple_seo_meta_robots_noarchive' => '0',
        'simple_seo_meta_robots_nocache' => '0',
        'simple_seo_meta_robots_nositelinkssearchbox' => '0',
        'simple_seo_meta_robots_max_snippet' => '',
        'simple_seo_meta_robots_max_image_preview' => '',
        'simple_seo_meta_robots_max_video_preview' => '',
        'simple_seo_meta_robots_unavailable_after' => '',

        'simple_seo_meta_enable_og' => '1',
        'simple_seo_meta_og_type' => 'website',
        'simple_seo_meta_og_locale' => '',
        'simple_seo_meta_default_image' => '',
        'simple_seo_meta_default_image_alt' => '',

        'simple_seo_meta_enable_twitter' => '1',
        'simple_seo_meta_twitter_card' => 'summary_large_image',
        'simple_seo_meta_twitter_site' => '',
        'simple_seo_meta_twitter_creator' => '',
    );

    protected $_recordMetaDefaults = array(
        'seo_title' => '',
        'meta_description' => '',
        'canonical_url' => '',

        'robots_base' => 'inherit',
        'robots_custom' => '',
        'robots_nosnippet' => '0',
        'robots_indexifembedded' => '0',
        'robots_notranslate' => '0',
        'robots_noimageindex' => '0',
        'robots_noarchive' => '0',
        'robots_nocache' => '0',
        'robots_nositelinkssearchbox' => '0',
        'robots_max_snippet' => '',
        'robots_max_image_preview' => '',
        'robots_max_video_preview' => '',
        'robots_unavailable_after' => '',

        'og_title' => '',
        'og_description' => '',
        'og_type' => '',
        'og_image' => '',
        'og_image_alt' => '',

        'twitter_card' => '',
        'twitter_title' => '',
        'twitter_description' => '',
        'twitter_image' => '',
        'twitter_image_alt' => '',
    );

    public function hookInstall($args)
    {
        foreach ($this->_defaults as $name => $value) {
            set_option($name, $value);
        }
        $this->createMetaTable();
    }

    public function hookUpgrade($args)
    {
        $this->createMetaTable();
    }

    public function hookUninstall($args)
    {
        foreach (array_keys($this->_defaults) as $name) {
            delete_option($name);
        }
        $this->dropMetaTable();
    }

    public function hookInitialize($args)
    {
        if (function_exists('add_translation_source')) {
            add_translation_source(dirname(__FILE__) . '/languages');
        }

        if (is_admin_theme()) {
            return;
        }

        if ($this->getOptionBool('simple_seo_meta_enable_title_rewrite')) {
            ob_start(array($this, 'filterPublicHtmlOutput'));
        }
    }

    public function hookPublicHead($args)
    {
        $meta = $this->buildCurrentPageMeta();
        $lines = array();

        if ($meta['description'] !== '') {
            $lines[] = $this->metaName('description', $meta['description']);
        }

        if ($meta['robots'] !== '') {
            $lines[] = $this->metaName('robots', $meta['robots']);
        }

        if ($this->getOptionBool('simple_seo_meta_enable_canonical') && $meta['canonical'] !== '') {
            $lines[] = '<link rel="canonical" href="' . $this->escape($meta['canonical']) . '">';
        }

        if ($this->getOptionBool('simple_seo_meta_enable_og')) {
            $lines[] = $this->metaProperty('og:title', $meta['og_title']);
            if ($meta['og_description'] !== '') {
                $lines[] = $this->metaProperty('og:description', $meta['og_description']);
            }
            $lines[] = $this->metaProperty('og:type', $meta['og_type']);
            if ($meta['canonical'] !== '') {
                $lines[] = $this->metaProperty('og:url', $meta['canonical']);
            }
            $siteName = $this->getSiteTitle();
            if ($siteName !== '') {
                $lines[] = $this->metaProperty('og:site_name', $siteName);
            }
            $locale = $this->getOptionTrimmed('simple_seo_meta_og_locale', '');
            if ($locale !== '') {
                $lines[] = $this->metaProperty('og:locale', $locale);
            }
            if ($meta['og_image'] !== '') {
                $lines[] = $this->metaProperty('og:image', $meta['og_image']);
                if ($meta['og_image_alt'] !== '') {
                    $lines[] = $this->metaProperty('og:image:alt', $meta['og_image_alt']);
                }
            }
        }

        if ($this->getOptionBool('simple_seo_meta_enable_twitter')) {
            $lines[] = $this->metaName('twitter:card', $meta['twitter_card']);
            $twitterSite = $this->normalizeTwitterHandle($this->getOptionTrimmed('simple_seo_meta_twitter_site', ''));
            $twitterCreator = $this->normalizeTwitterHandle($this->getOptionTrimmed('simple_seo_meta_twitter_creator', ''));
            if ($twitterSite !== '') {
                $lines[] = $this->metaName('twitter:site', $twitterSite);
            }
            if ($twitterCreator !== '') {
                $lines[] = $this->metaName('twitter:creator', $twitterCreator);
            }
            $lines[] = $this->metaName('twitter:title', $meta['twitter_title']);
            if ($meta['twitter_description'] !== '') {
                $lines[] = $this->metaName('twitter:description', $meta['twitter_description']);
            }
            if ($meta['twitter_image'] !== '') {
                $lines[] = $this->metaName('twitter:image', $meta['twitter_image']);
                if ($meta['twitter_image_alt'] !== '') {
                    $lines[] = $this->metaName('twitter:image:alt', $meta['twitter_image_alt']);
                }
            }
        }

        if (!empty($lines)) {
            echo "\n<!-- Simple SEO Meta -->\n";
            echo implode("\n", $lines);
            echo "\n<!-- /Simple SEO Meta -->\n";
        }
    }

    public function hookAdminSimplePagesPageForm($args)
    {
        if (!isset($args['form'])) {
            return;
        }

        $form = $args['form'];
        $record = isset($args['record']) ? $args['record'] : null;
        $meta = $this->_recordMetaDefaults;

        if ($record && isset($record->id) && $record->id) {
            $meta = array_merge($meta, $this->getRecordMeta('SimplePagesPage', (int) $record->id));
        }

        $form->addElementToEditGroup('note', 'simple_seo_meta_heading', array(
            'value' => '<div id="simple-seo-meta-fields"><h2>' . $this->escape(__('Simple SEO Meta')) . '</h2><p>' . $this->escape(__('Optional SEO metadata for this Simple Page. Empty fields use this plugin\'s global defaults and automatic fallbacks.')) . '</p></div>',
            'decorators' => array('ViewHelper'),
        ));

        $form->addElementToEditGroup('text', 'simple_seo_meta_seo_title', array(
            'label' => __('SEO title'),
            'description' => __('Optional. If empty, the page title and the global title template are used.'),
            'value' => $meta['seo_title'],
            'size' => 60,
        ));

        $form->addElementToEditGroup('textarea', 'simple_seo_meta_meta_description', array(
            'label' => __('Meta description'),
            'description' => __('Optional. Recommended length: about 150–160 characters.'),
            'value' => $meta['meta_description'],
            'rows' => 3,
            'cols' => 60,
        ));

        $form->addElementToEditGroup('text', 'simple_seo_meta_canonical_url', array(
            'label' => __('Canonical URL'),
            'description' => __('Optional. Leave empty to use the automatic canonical URL.'),
            'value' => $meta['canonical_url'],
            'size' => 60,
        ));

        $form->addElementToEditGroup('select', 'simple_seo_meta_robots_base', array(
            'label' => __('Meta robots'),
            'description' => __('Choose the main robots directive for this page.'),
            'multiOptions' => $this->getRecordRobotsBaseOptions(),
            'value' => $meta['robots_base'],
        ));

        $form->addElementToEditGroup('text', 'simple_seo_meta_robots_custom', array(
            'label' => __('Custom robots value'),
            'description' => __('Only used when Meta robots is set to Custom value. Example: noindex, nofollow, max-snippet:0'),
            'value' => $meta['robots_custom'],
            'size' => 60,
        ));

        $this->addCheckboxToEditGroup($form, 'simple_seo_meta_robots_nosnippet', __('robots: nosnippet'), __('Do not show a text snippet or video preview in search results.'), $meta['robots_nosnippet']);
        $this->addCheckboxToEditGroup($form, 'simple_seo_meta_robots_indexifembedded', __('robots: indexifembedded'), __('Allow indexing when this page is embedded elsewhere, normally used with noindex.'), $meta['robots_indexifembedded']);
        $this->addCheckboxToEditGroup($form, 'simple_seo_meta_robots_notranslate', __('robots: notranslate'), __('Do not offer translation of this page in search results.'), $meta['robots_notranslate']);
        $this->addCheckboxToEditGroup($form, 'simple_seo_meta_robots_noimageindex', __('robots: noimageindex'), __('Do not index images on this page.'), $meta['robots_noimageindex']);
        $this->addCheckboxToEditGroup($form, 'simple_seo_meta_robots_noarchive', __('robots: noarchive'), __('Historical directive. Prevents showing a cached link where supported.'), $meta['robots_noarchive']);
        $this->addCheckboxToEditGroup($form, 'simple_seo_meta_robots_nocache', __('robots: nocache'), __('Historical directive, mainly associated with some non-Google crawlers.'), $meta['robots_nocache']);
        $this->addCheckboxToEditGroup($form, 'simple_seo_meta_robots_nositelinkssearchbox', __('robots: nositelinkssearchbox'), __('Historical directive. Google no longer supports the sitelinks search box result.'), $meta['robots_nositelinkssearchbox']);

        $form->addElementToEditGroup('text', 'simple_seo_meta_robots_max_snippet', array(
            'label' => __('robots: max-snippet'),
            'description' => __('Optional integer. 0 is equivalent to nosnippet; -1 means no limit.'),
            'value' => $meta['robots_max_snippet'],
            'size' => 10,
        ));

        $form->addElementToEditGroup('select', 'simple_seo_meta_robots_max_image_preview', array(
            'label' => __('robots: max-image-preview'),
            'description' => __('Optional image preview limit.'),
            'multiOptions' => $this->getMaxImagePreviewOptions(),
            'value' => $meta['robots_max_image_preview'],
        ));

        $form->addElementToEditGroup('text', 'simple_seo_meta_robots_max_video_preview', array(
            'label' => __('robots: max-video-preview'),
            'description' => __('Optional integer in seconds. 0 allows at most a static image; -1 means no limit.'),
            'value' => $meta['robots_max_video_preview'],
            'size' => 10,
        ));

        $form->addElementToEditGroup('text', 'simple_seo_meta_robots_unavailable_after', array(
            'label' => __('robots: unavailable_after'),
            'description' => __('Optional date/time. Example: Wed, 31 Dec 2026 23:59:59 GMT.'),
            'value' => $meta['robots_unavailable_after'],
            'size' => 40,
        ));

        $form->addElementToEditGroup('text', 'simple_seo_meta_og_title', array(
            'label' => __('Open Graph title'),
            'description' => __('Optional. If empty, the SEO title is used.'),
            'value' => $meta['og_title'],
            'size' => 60,
        ));

        $form->addElementToEditGroup('textarea', 'simple_seo_meta_og_description', array(
            'label' => __('Open Graph description'),
            'description' => __('Optional. If empty, the meta description is used.'),
            'value' => $meta['og_description'],
            'rows' => 2,
            'cols' => 60,
        ));

        $form->addElementToEditGroup('text', 'simple_seo_meta_og_type', array(
            'label' => __('Open Graph type'),
            'description' => __('Optional. Example: website, article.'),
            'value' => $meta['og_type'],
            'size' => 30,
        ));

        $form->addElementToEditGroup('text', 'simple_seo_meta_og_image', array(
            'label' => __('Open Graph image URL'),
            'description' => __('Optional absolute or root-relative URL.'),
            'value' => $meta['og_image'],
            'size' => 60,
        ));

        $form->addElementToEditGroup('text', 'simple_seo_meta_og_image_alt', array(
            'label' => __('Open Graph image alt text'),
            'description' => __('Optional text alternative for the social preview image.'),
            'value' => $meta['og_image_alt'],
            'size' => 60,
        ));

        $form->addElementToEditGroup('select', 'simple_seo_meta_twitter_card', array(
            'label' => __('Twitter/X Card type'),
            'description' => __('Optional. If empty, the global card type is used.'),
            'multiOptions' => $this->getRecordTwitterCardOptions(),
            'value' => $meta['twitter_card'],
        ));

        $form->addElementToEditGroup('text', 'simple_seo_meta_twitter_title', array(
            'label' => __('Twitter/X title'),
            'description' => __('Optional. If empty, the Open Graph title or SEO title is used.'),
            'value' => $meta['twitter_title'],
            'size' => 60,
        ));

        $form->addElementToEditGroup('textarea', 'simple_seo_meta_twitter_description', array(
            'label' => __('Twitter/X description'),
            'description' => __('Optional. If empty, the Open Graph description or meta description is used.'),
            'value' => $meta['twitter_description'],
            'rows' => 2,
            'cols' => 60,
        ));

        $form->addElementToEditGroup('text', 'simple_seo_meta_twitter_image', array(
            'label' => __('Twitter/X image URL'),
            'description' => __('Optional absolute or root-relative URL.'),
            'value' => $meta['twitter_image'],
            'size' => 60,
        ));

        $form->addElementToEditGroup('text', 'simple_seo_meta_twitter_image_alt', array(
            'label' => __('Twitter/X image alt text'),
            'description' => __('Optional text alternative for the Twitter/X image.'),
            'value' => $meta['twitter_image_alt'],
            'size' => 60,
        ));
    }

    public function hookAdminHead($args)
    {
        if (!is_admin_theme()) {
            return;
        }

        $cssPath = dirname(__FILE__) . '/css/simple-seo-meta-admin.css';
        if (is_readable($cssPath)) {
            echo '<style id="simple-seo-meta-admin-css">' . "\n";
            echo file_get_contents($cssPath);
            echo "\n" . '</style>' . "\n";
        }
    }

    public function hookAdminFooter($args)
    {
        if (!is_admin_theme() || !class_exists('SimplePagesPage')) {
            return;
        }

        $record = $this->getAdminSimplePagesRecord();
        $meta = $this->_recordMetaDefaults;
        if ($record && isset($record->id) && $record->id) {
            $meta = array_merge($meta, $this->getRecordMeta('SimplePagesPage', (int) $record->id));
        }

        $html = $this->renderSimplePagesFallbackFields($meta);
        $jsPath = dirname(__FILE__) . '/javascripts/simple-seo-meta-admin.js';
        ?>
        <div id="simple-seo-meta-template" hidden>
            <?php echo $html; ?>
        </div>
        <?php if (is_readable($jsPath)): ?>
        <script id="simple-seo-meta-admin-js">
        <?php echo file_get_contents($jsPath); ?>
        </script>
        <?php endif; ?>
        <?php
    }

    public function hookAfterSaveRecord($args)
    {
        $this->handleSimplePagesSeoSave($args);
    }

    public function hookAfterSaveSimplePagesPage($args)
    {
        $this->handleSimplePagesSeoSave($args);
    }

    protected function handleSimplePagesSeoSave($args)
    {
        if (empty($args['record'])) {
            return;
        }

        $record = $args['record'];
        if (!$this->isSimplePagesPageRecord($record) || empty($record->id)) {
            return;
        }

        $post = array();
        if (isset($args['post']) && is_array($args['post'])) {
            $post = $args['post'];
        } elseif (!empty($_POST) && is_array($_POST)) {
            $post = $_POST;
        }

        if (!$this->postContainsSimpleSeoMetaFields($post)) {
            return;
        }

        $this->saveRecordMetaFromPost('SimplePagesPage', (int) $record->id, $post);
    }

    protected function isSimplePagesPageRecord($record)
    {
        if (!is_object($record)) {
            return false;
        }

        if (class_exists('SimplePagesPage') && $record instanceof SimplePagesPage) {
            return true;
        }

        return get_class($record) === 'SimplePagesPage';
    }

    protected function postContainsSimpleSeoMetaFields($post)
    {
        if (!is_array($post)) {
            return false;
        }

        if (isset($post['simple_seo_meta_fields_present']) && (string) $post['simple_seo_meta_fields_present'] === '1') {
            return true;
        }

        foreach (array_keys($this->_recordMetaDefaults) as $key) {
            if (array_key_exists('simple_seo_meta_' . $key, $post)) {
                return true;
            }
        }

        return false;
    }

    public function hookConfigForm($args)
    {
        $robotsBaseOptions = $this->getGlobalRobotsBaseOptions();
        $maxImagePreviewOptions = $this->getMaxImagePreviewOptions();
        $twitterCardOptions = $this->getGlobalTwitterCardOptions();

        ?>
        <fieldset id="simple-seo-meta-basic">
            <legend><?php echo __('Global SEO defaults'); ?></legend>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_enable_title_rewrite"><?php echo __('Rewrite title tag'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->checkbox('simple_seo_meta_enable_title_rewrite'); ?>
                    <p class="explanation"><?php echo __('When enabled, Simple SEO Meta replaces the first public &lt;title&gt; tag generated by the theme.'); ?></p>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_title_template"><?php echo __('Title template'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->text('simple_seo_meta_title_template', 70); ?>
                    <p class="explanation"><?php echo __('Available variables: {page_title}, {site_title}. Example: {page_title} | {site_title}'); ?></p>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_default_title"><?php echo __('Default page title'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->text('simple_seo_meta_default_title', 70); ?>
                    <p class="explanation"><?php echo __('Fallback title used when the plugin cannot infer a page title from the current record. Individual Simple Pages can override this value.'); ?></p>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_default_description"><?php echo __('Default meta description'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->textarea('simple_seo_meta_default_description', 70, 4); ?>
                    <p class="explanation"><?php echo __('Fallback description used when the plugin cannot infer a description from Dublin Core Description or Simple Pages text. Individual Simple Pages can override this value. Recommended length: about 150–160 characters.'); ?></p>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_enable_canonical"><?php echo __('Canonical URL'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->checkbox('simple_seo_meta_enable_canonical'); ?>
                    <p class="explanation"><?php echo __('Output a canonical URL for the current public page.'); ?></p>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_strip_query_from_canonical"><?php echo __('Strip query string from canonical'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->checkbox('simple_seo_meta_strip_query_from_canonical'); ?>
                    <p class="explanation"><?php echo __('Recommended for most Omeka public pages.'); ?></p>
                </div>
            </div>
        </fieldset>

        <fieldset id="simple-seo-meta-robots">
            <legend><?php echo __('Global meta robots defaults'); ?></legend>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_robots_base"><?php echo __('Base directive'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->select('simple_seo_meta_robots_base', $robotsBaseOptions); ?>
                    <p class="explanation"><?php echo __('Choose the global default directive. Leave empty to avoid outputting a robots meta tag; that normally means the default crawler behaviour, equivalent to index,follow. Individual Simple Pages can inherit or override this setting.'); ?></p>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_robots_custom"><?php echo __('Custom robots value'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->text('simple_seo_meta_robots_custom', 70); ?>
                    <p class="explanation"><?php echo __('Only used when Base directive is set to “Custom value”. Example: noindex, nofollow, max-snippet:0'); ?></p>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label><?php echo __('Additional directives'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <label><?php echo $this->checkbox('simple_seo_meta_robots_nosnippet'); ?> nosnippet</label><br>
                    <label><?php echo $this->checkbox('simple_seo_meta_robots_indexifembedded'); ?> indexifembedded</label><br>
                    <label><?php echo $this->checkbox('simple_seo_meta_robots_notranslate'); ?> notranslate</label><br>
                    <label><?php echo $this->checkbox('simple_seo_meta_robots_noimageindex'); ?> noimageindex</label><br>
                    <label><?php echo $this->checkbox('simple_seo_meta_robots_noarchive'); ?> noarchive</label><br>
                    <label><?php echo $this->checkbox('simple_seo_meta_robots_nocache'); ?> nocache</label><br>
                    <label><?php echo $this->checkbox('simple_seo_meta_robots_nositelinkssearchbox'); ?> nositelinkssearchbox</label>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_robots_max_snippet"><?php echo __('max-snippet'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->text('simple_seo_meta_robots_max_snippet', 20); ?>
                    <p class="explanation"><?php echo __('Leave empty, or use an integer. 0 is equivalent to nosnippet; -1 lets the crawler choose the snippet length.'); ?></p>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_robots_max_image_preview"><?php echo __('max-image-preview'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->select('simple_seo_meta_robots_max_image_preview', $maxImagePreviewOptions); ?>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_robots_max_video_preview"><?php echo __('max-video-preview'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->text('simple_seo_meta_robots_max_video_preview', 20); ?>
                    <p class="explanation"><?php echo __('Leave empty, or use an integer. 0 allows at most a static image; -1 means no limit.'); ?></p>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_robots_unavailable_after"><?php echo __('unavailable_after'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->text('simple_seo_meta_robots_unavailable_after', 40); ?>
                    <p class="explanation"><?php echo __('Optional date/time. Example: Wed, 31 Dec 2026 23:59:59 GMT.'); ?></p>
                </div>
            </div>
        </fieldset>

        <fieldset id="simple-seo-meta-og">
            <legend><?php echo __('Open Graph'); ?></legend>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_enable_og"><?php echo __('Enable Open Graph'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->checkbox('simple_seo_meta_enable_og'); ?>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_og_type"><?php echo __('Default og:type'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->text('simple_seo_meta_og_type', 40); ?>
                    <p class="explanation"><?php echo __('Usually website. You may also use article or another Open Graph type.'); ?></p>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_og_locale"><?php echo __('og:locale'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->text('simple_seo_meta_og_locale', 20); ?>
                    <p class="explanation"><?php echo __('Optional. Example: es_ES, ca_ES, en_GB.'); ?></p>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_default_image"><?php echo __('Default image URL'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->text('simple_seo_meta_default_image', 70); ?>
                    <p class="explanation"><?php echo __('Absolute or root-relative URL. For item pages, the plugin tries to use the first image file before this fallback.'); ?></p>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_default_image_alt"><?php echo __('Default image alt text'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->text('simple_seo_meta_default_image_alt', 70); ?>
                </div>
            </div>
        </fieldset>

        <fieldset id="simple-seo-meta-twitter">
            <legend><?php echo __('Twitter Cards'); ?></legend>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_enable_twitter"><?php echo __('Enable Twitter Cards'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->checkbox('simple_seo_meta_enable_twitter'); ?>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_twitter_card"><?php echo __('Card type'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->select('simple_seo_meta_twitter_card', $twitterCardOptions); ?>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_twitter_site"><?php echo __('twitter:site'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->text('simple_seo_meta_twitter_site', 40); ?>
                    <p class="explanation"><?php echo __('Optional X/Twitter handle. Example: @your_site'); ?></p>
                </div>
            </div>

            <div class="field">
                <div class="two columns alpha">
                    <label for="simple_seo_meta_twitter_creator"><?php echo __('twitter:creator'); ?></label>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->text('simple_seo_meta_twitter_creator', 40); ?>
                    <p class="explanation"><?php echo __('Optional author or project handle. Example: @creator'); ?></p>
                </div>
            </div>
        </fieldset>
        <?php
    }

    public function hookConfig($args)
    {
        $post = isset($args['post']) ? $args['post'] : array();

        $checkboxes = array(
            'simple_seo_meta_enable_title_rewrite',
            'simple_seo_meta_enable_canonical',
            'simple_seo_meta_strip_query_from_canonical',
            'simple_seo_meta_robots_nosnippet',
            'simple_seo_meta_robots_indexifembedded',
            'simple_seo_meta_robots_notranslate',
            'simple_seo_meta_robots_noimageindex',
            'simple_seo_meta_robots_noarchive',
            'simple_seo_meta_robots_nocache',
            'simple_seo_meta_robots_nositelinkssearchbox',
            'simple_seo_meta_enable_og',
            'simple_seo_meta_enable_twitter',
        );

        foreach ($checkboxes as $name) {
            set_option($name, isset($post[$name]) ? '1' : '0');
        }

        $allowedSelects = array(
            'simple_seo_meta_robots_base' => array_keys($this->getGlobalRobotsBaseOptions()),
            'simple_seo_meta_robots_max_image_preview' => array_keys($this->getMaxImagePreviewOptions()),
            'simple_seo_meta_twitter_card' => array_keys($this->getGlobalTwitterCardOptions()),
        );

        foreach ($allowedSelects as $name => $allowedValues) {
            $value = isset($post[$name]) ? trim($post[$name]) : '';
            if (!in_array($value, $allowedValues)) {
                $value = '';
            }
            set_option($name, $value);
        }

        $textFields = array(
            'simple_seo_meta_title_template',
            'simple_seo_meta_default_title',
            'simple_seo_meta_default_description',
            'simple_seo_meta_robots_custom',
            'simple_seo_meta_robots_max_snippet',
            'simple_seo_meta_robots_max_video_preview',
            'simple_seo_meta_robots_unavailable_after',
            'simple_seo_meta_og_type',
            'simple_seo_meta_og_locale',
            'simple_seo_meta_default_image',
            'simple_seo_meta_default_image_alt',
            'simple_seo_meta_twitter_site',
            'simple_seo_meta_twitter_creator',
        );

        foreach ($textFields as $name) {
            $value = isset($post[$name]) ? trim($post[$name]) : '';
            set_option($name, $value);
        }
    }

    public function filterPublicHtmlOutput($buffer)
    {
        if (is_admin_theme()) {
            return $buffer;
        }

        if (stripos($buffer, '<html') === false || stripos($buffer, '</head>') === false) {
            return $buffer;
        }

        $meta = $this->buildCurrentPageMeta();
        $titleTag = '<title>' . $this->escape($meta['title']) . '</title>';

        $count = 0;
        $buffer = preg_replace('/<title\b[^>]*>.*?<\/title>/is', $titleTag, $buffer, 1, $count);

        if (!$count) {
            $buffer = preg_replace('/<\/head>/i', $titleTag . "\n</head>", $buffer, 1);
        }

        return $buffer;
    }

    protected function buildCurrentPageMeta()
    {
        $context = $this->getCurrentRecordContext();
        $recordMeta = array();
        if ($context) {
            $recordMeta = $this->getRecordMeta($context['record_type'], (int) $context['record_id']);
        }
        $recordMeta = array_merge($this->_recordMetaDefaults, $recordMeta);

        $siteTitle = $this->getSiteTitle();
        $pageTitle = $this->getCurrentPageTitle($context);

        if ($pageTitle === '') {
            $pageTitle = $this->getOptionTrimmed('simple_seo_meta_default_title', '');
        }
        if ($pageTitle === '') {
            $pageTitle = $siteTitle;
        }

        if ($recordMeta['seo_title'] !== '') {
            $title = $this->normalizeText($recordMeta['seo_title']);
        } else {
            $titleTemplate = $this->getOptionTrimmed('simple_seo_meta_title_template', '{page_title} | {site_title}');
            if ($titleTemplate === '') {
                $titleTemplate = '{page_title}';
            }
            $title = str_replace(
                array('{page_title}', '{site_title}'),
                array($pageTitle, $siteTitle),
                $titleTemplate
            );
            $title = $this->normalizeText($title);
        }

        $description = $recordMeta['meta_description'];
        if ($description === '') {
            $description = $this->getCurrentPageDescription($context);
        }
        if ($description === '') {
            $description = $this->getOptionTrimmed('simple_seo_meta_default_description', '');
        }
        $description = $this->normalizeText($description);

        $canonical = $recordMeta['canonical_url'];
        if ($canonical !== '') {
            $canonical = $this->absoluteUrl($canonical);
        } else {
            $canonical = $this->getCanonicalUrl();
        }

        $image = $this->getCurrentPageImageUrl($context);
        if ($image === '') {
            $image = $this->getOptionTrimmed('simple_seo_meta_default_image', '');
        }
        $image = $this->absoluteUrl($image);

        $imageAlt = $this->getCurrentPageImageAlt($context);
        if ($imageAlt === '') {
            $imageAlt = $this->getOptionTrimmed('simple_seo_meta_default_image_alt', '');
        }
        $imageAlt = $this->normalizeText($imageAlt);

        $ogTitle = $recordMeta['og_title'] !== '' ? $recordMeta['og_title'] : $title;
        $ogDescription = $recordMeta['og_description'] !== '' ? $recordMeta['og_description'] : $description;
        $ogType = $recordMeta['og_type'] !== '' ? $recordMeta['og_type'] : $this->getOptionTrimmed('simple_seo_meta_og_type', 'website');
        if ($ogType === '') {
            $ogType = 'website';
        }
        $ogImage = $recordMeta['og_image'] !== '' ? $recordMeta['og_image'] : $image;
        $ogImageAlt = $recordMeta['og_image_alt'] !== '' ? $recordMeta['og_image_alt'] : $imageAlt;

        $twitterCard = $recordMeta['twitter_card'] !== '' ? $recordMeta['twitter_card'] : $this->getOptionTrimmed('simple_seo_meta_twitter_card', 'summary_large_image');
        if ($twitterCard === '') {
            $twitterCard = 'summary_large_image';
        }
        $twitterTitle = $recordMeta['twitter_title'] !== '' ? $recordMeta['twitter_title'] : $ogTitle;
        $twitterDescription = $recordMeta['twitter_description'] !== '' ? $recordMeta['twitter_description'] : $ogDescription;
        $twitterImage = $recordMeta['twitter_image'] !== '' ? $recordMeta['twitter_image'] : $ogImage;
        $twitterImageAlt = $recordMeta['twitter_image_alt'] !== '' ? $recordMeta['twitter_image_alt'] : $ogImageAlt;

        return array(
            'title' => $title,
            'description' => $description,
            'robots' => $this->buildRobotsForCurrentPage($recordMeta),
            'canonical' => $canonical,
            'og_title' => $this->normalizeText($ogTitle),
            'og_description' => $this->normalizeText($ogDescription),
            'og_type' => $this->sanitizeOpenGraphType($ogType),
            'og_image' => $this->absoluteUrl($ogImage),
            'og_image_alt' => $this->normalizeText($ogImageAlt),
            'twitter_card' => $this->sanitizeTwitterCard($twitterCard),
            'twitter_title' => $this->normalizeText($twitterTitle),
            'twitter_description' => $this->normalizeText($twitterDescription),
            'twitter_image' => $this->absoluteUrl($twitterImage),
            'twitter_image_alt' => $this->normalizeText($twitterImageAlt),
        );
    }

    protected function buildRobotsForCurrentPage($recordMeta)
    {
        if (isset($recordMeta['robots_base']) && $recordMeta['robots_base'] !== 'inherit') {
            return $this->buildRobotsContentFromValues(
                $recordMeta['robots_base'],
                $recordMeta['robots_custom'],
                array(
                    'nosnippet' => $recordMeta['robots_nosnippet'],
                    'indexifembedded' => $recordMeta['robots_indexifembedded'],
                    'notranslate' => $recordMeta['robots_notranslate'],
                    'noimageindex' => $recordMeta['robots_noimageindex'],
                    'noarchive' => $recordMeta['robots_noarchive'],
                    'nocache' => $recordMeta['robots_nocache'],
                    'nositelinkssearchbox' => $recordMeta['robots_nositelinkssearchbox'],
                    'max_snippet' => $recordMeta['robots_max_snippet'],
                    'max_image_preview' => $recordMeta['robots_max_image_preview'],
                    'max_video_preview' => $recordMeta['robots_max_video_preview'],
                    'unavailable_after' => $recordMeta['robots_unavailable_after'],
                )
            );
        }

        return $this->buildRobotsContent();
    }

    protected function buildRobotsContent()
    {
        return $this->buildRobotsContentFromValues(
            $this->getOptionTrimmed('simple_seo_meta_robots_base', ''),
            $this->getOptionTrimmed('simple_seo_meta_robots_custom', ''),
            array(
                'nosnippet' => $this->getOptionBool('simple_seo_meta_robots_nosnippet') ? '1' : '0',
                'indexifembedded' => $this->getOptionBool('simple_seo_meta_robots_indexifembedded') ? '1' : '0',
                'notranslate' => $this->getOptionBool('simple_seo_meta_robots_notranslate') ? '1' : '0',
                'noimageindex' => $this->getOptionBool('simple_seo_meta_robots_noimageindex') ? '1' : '0',
                'noarchive' => $this->getOptionBool('simple_seo_meta_robots_noarchive') ? '1' : '0',
                'nocache' => $this->getOptionBool('simple_seo_meta_robots_nocache') ? '1' : '0',
                'nositelinkssearchbox' => $this->getOptionBool('simple_seo_meta_robots_nositelinkssearchbox') ? '1' : '0',
                'max_snippet' => $this->getOptionTrimmed('simple_seo_meta_robots_max_snippet', ''),
                'max_image_preview' => $this->getOptionTrimmed('simple_seo_meta_robots_max_image_preview', ''),
                'max_video_preview' => $this->getOptionTrimmed('simple_seo_meta_robots_max_video_preview', ''),
                'unavailable_after' => $this->getOptionTrimmed('simple_seo_meta_robots_unavailable_after', ''),
            )
        );
    }

    protected function buildRobotsContentFromValues($base, $custom, $values)
    {
        $base = trim((string) $base);

        if ($base === 'custom') {
            return $this->sanitizeRobotsCustomValue($custom);
        }

        if ($base === 'inherit') {
            return $this->buildRobotsContent();
        }

        if ($base === 'none') {
            $base = 'noindex,nofollow';
        }

        $directives = array();
        if ($base !== '') {
            foreach (explode(',', $base) as $directive) {
                $directive = trim($directive);
                if ($directive !== '') {
                    $directives[] = $directive;
                }
            }
        }

        $booleanDirectives = array(
            'nosnippet' => 'nosnippet',
            'indexifembedded' => 'indexifembedded',
            'notranslate' => 'notranslate',
            'noimageindex' => 'noimageindex',
            'noarchive' => 'noarchive',
            'nocache' => 'nocache',
            'nositelinkssearchbox' => 'nositelinkssearchbox',
        );

        foreach ($booleanDirectives as $key => $directive) {
            if (isset($values[$key]) && (string) $values[$key] === '1') {
                $directives[] = $directive;
            }
        }

        $maxSnippet = isset($values['max_snippet']) ? $this->sanitizeIntegerLikeDirectiveValue($values['max_snippet']) : '';
        if ($maxSnippet !== '') {
            $directives[] = 'max-snippet:' . $maxSnippet;
        }

        $maxImagePreview = isset($values['max_image_preview']) ? trim((string) $values['max_image_preview']) : '';
        if (in_array($maxImagePreview, array('none', 'standard', 'large'))) {
            $directives[] = 'max-image-preview:' . $maxImagePreview;
        }

        $maxVideoPreview = isset($values['max_video_preview']) ? $this->sanitizeIntegerLikeDirectiveValue($values['max_video_preview']) : '';
        if ($maxVideoPreview !== '') {
            $directives[] = 'max-video-preview:' . $maxVideoPreview;
        }

        $unavailableAfter = isset($values['unavailable_after']) ? trim((string) $values['unavailable_after']) : '';
        if ($unavailableAfter !== '') {
            $directives[] = 'unavailable_after: ' . $this->sanitizeRobotsDate($unavailableAfter);
        }

        $directives = array_unique($directives);

        if (count($directives) > 1 && in_array('all', $directives)) {
            $directives = array_values(array_diff($directives, array('all')));
        }

        return implode(', ', $directives);
    }

    protected function getGlobalRobotsBaseOptions()
    {
        return array(
            '' => __('Default: no robots meta tag (normally index,follow)'),
            'all' => __('all (no restrictions; equivalent to index,follow)'),
            'index,follow' => __('index,follow'),
            'index,nofollow' => __('index,nofollow'),
            'noindex,follow' => __('noindex,follow'),
            'noindex,nofollow' => __('noindex,nofollow'),
            'noindex' => __('noindex'),
            'nofollow' => __('nofollow'),
            'none' => __('none (shortcut for noindex,nofollow)'),
            'custom' => __('Custom value'),
        );
    }

    protected function getRecordRobotsBaseOptions()
    {
        return array_merge(array('inherit' => __('Use global setting / automatic fallback')), $this->getGlobalRobotsBaseOptions());
    }

    protected function getMaxImagePreviewOptions()
    {
        return array(
            '' => __('Do not output'),
            'none' => __('none'),
            'standard' => __('standard'),
            'large' => __('large'),
        );
    }

    protected function getGlobalTwitterCardOptions()
    {
        return array(
            'summary' => __('summary'),
            'summary_large_image' => __('summary_large_image'),
        );
    }

    protected function getRecordTwitterCardOptions()
    {
        return array_merge(array('' => __('Use global setting')), $this->getGlobalTwitterCardOptions());
    }

    protected function addCheckboxToEditGroup($form, $name, $label, $description, $value)
    {
        $form->addElementToEditGroup('checkbox', $name, array(
            'label' => $label,
            'description' => $description,
            'checked' => ((string) $value === '1'),
            'values' => array(1, 0),
        ));
    }

    protected function saveRecordMetaFromPost($recordType, $recordId, $post)
    {
        $this->createMetaTable();

        $data = array(
            'seo_title' => $this->sanitizeAdminText($this->getPostValue($post, 'simple_seo_meta_seo_title')),
            'meta_description' => $this->sanitizeAdminText($this->getPostValue($post, 'simple_seo_meta_meta_description')),
            'canonical_url' => $this->sanitizeUrlLike($this->getPostValue($post, 'simple_seo_meta_canonical_url')),

            'robots_base' => $this->sanitizeRecordRobotsBase($this->getPostValue($post, 'simple_seo_meta_robots_base')),
            'robots_custom' => $this->sanitizeRobotsCustomValue($this->getPostValue($post, 'simple_seo_meta_robots_custom')),
            'robots_nosnippet' => $this->getPostCheckbox($post, 'simple_seo_meta_robots_nosnippet'),
            'robots_indexifembedded' => $this->getPostCheckbox($post, 'simple_seo_meta_robots_indexifembedded'),
            'robots_notranslate' => $this->getPostCheckbox($post, 'simple_seo_meta_robots_notranslate'),
            'robots_noimageindex' => $this->getPostCheckbox($post, 'simple_seo_meta_robots_noimageindex'),
            'robots_noarchive' => $this->getPostCheckbox($post, 'simple_seo_meta_robots_noarchive'),
            'robots_nocache' => $this->getPostCheckbox($post, 'simple_seo_meta_robots_nocache'),
            'robots_nositelinkssearchbox' => $this->getPostCheckbox($post, 'simple_seo_meta_robots_nositelinkssearchbox'),
            'robots_max_snippet' => $this->sanitizeIntegerLikeDirectiveValue($this->getPostValue($post, 'simple_seo_meta_robots_max_snippet')),
            'robots_max_image_preview' => $this->sanitizeMaxImagePreview($this->getPostValue($post, 'simple_seo_meta_robots_max_image_preview')),
            'robots_max_video_preview' => $this->sanitizeIntegerLikeDirectiveValue($this->getPostValue($post, 'simple_seo_meta_robots_max_video_preview')),
            'robots_unavailable_after' => $this->sanitizeRobotsDate($this->getPostValue($post, 'simple_seo_meta_robots_unavailable_after')),

            'og_title' => $this->sanitizeAdminText($this->getPostValue($post, 'simple_seo_meta_og_title')),
            'og_description' => $this->sanitizeAdminText($this->getPostValue($post, 'simple_seo_meta_og_description')),
            'og_type' => $this->sanitizeOpenGraphType($this->getPostValue($post, 'simple_seo_meta_og_type')),
            'og_image' => $this->sanitizeUrlLike($this->getPostValue($post, 'simple_seo_meta_og_image')),
            'og_image_alt' => $this->sanitizeAdminText($this->getPostValue($post, 'simple_seo_meta_og_image_alt')),

            'twitter_card' => $this->sanitizeRecordTwitterCard($this->getPostValue($post, 'simple_seo_meta_twitter_card')),
            'twitter_title' => $this->sanitizeAdminText($this->getPostValue($post, 'simple_seo_meta_twitter_title')),
            'twitter_description' => $this->sanitizeAdminText($this->getPostValue($post, 'simple_seo_meta_twitter_description')),
            'twitter_image' => $this->sanitizeUrlLike($this->getPostValue($post, 'simple_seo_meta_twitter_image')),
            'twitter_image_alt' => $this->sanitizeAdminText($this->getPostValue($post, 'simple_seo_meta_twitter_image_alt')),
        );

        $data = array_merge($this->_recordMetaDefaults, $data);

        if (!$this->recordMetaHasCustomData($data)) {
            $this->deleteRecordMeta($recordType, $recordId);
            return;
        }

        $db = $this->_db;
        $table = $this->getMetaTableName();
        $json = json_encode($data);

        $sql = "INSERT INTO `$table` (`record_type`, `record_id`, `data`, `inserted`, `updated`) "
             . "VALUES (" . $db->quote($recordType) . ", " . (int) $recordId . ", " . $db->quote($json) . ", NOW(), NOW()) "
             . "ON DUPLICATE KEY UPDATE `data` = VALUES(`data`), `updated` = NOW()";
        $db->query($sql);
    }

    protected function recordMetaHasCustomData($data)
    {
        $defaults = $this->_recordMetaDefaults;
        foreach ($data as $key => $value) {
            $default = isset($defaults[$key]) ? $defaults[$key] : '';
            if ((string) $value !== (string) $default) {
                return true;
            }
        }
        return false;
    }

    protected function getRecordMeta($recordType, $recordId)
    {
        $db = $this->_db;
        $table = $this->getMetaTableName();
        $sql = "SELECT `data` FROM `$table` WHERE `record_type` = " . $db->quote($recordType) . " AND `record_id` = " . (int) $recordId . " LIMIT 1";

        try {
            $json = $db->fetchOne($sql);
        } catch (Exception $e) {
            return array();
        }

        if (!$json) {
            return array();
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return array();
        }

        return array_intersect_key($data, $this->_recordMetaDefaults);
    }

    protected function deleteRecordMeta($recordType, $recordId)
    {
        $db = $this->_db;
        $table = $this->getMetaTableName();
        $sql = "DELETE FROM `$table` WHERE `record_type` = " . $db->quote($recordType) . " AND `record_id` = " . (int) $recordId;
        try {
            $db->query($sql);
        } catch (Exception $e) {
            // Ignore deletion failures to avoid interrupting the original save.
        }
    }

    protected function isAdminSimplePagesEditOrAdd()
    {
        if (!is_admin_theme() || !class_exists('SimplePagesPage')) {
            return false;
        }

        try {
            $request = Zend_Controller_Front::getInstance()->getRequest();
            if (!$request) {
                return false;
            }

            return $request->getModuleName() === 'simple-pages'
                && $request->getControllerName() === 'index'
                && in_array($request->getActionName(), array('add', 'edit'));
        } catch (Exception $e) {
            return false;
        }
    }

    protected function getAdminSimplePagesRecord()
    {
        if (!class_exists('SimplePagesPage')) {
            return false;
        }

        try {
            $request = Zend_Controller_Front::getInstance()->getRequest();
            if (!$request || $request->getActionName() !== 'edit') {
                return false;
            }
            $pageId = (int) $request->getParam('id');
            if (!$pageId) {
                return false;
            }
            return get_db()->getTable('SimplePagesPage')->find($pageId);
        } catch (Exception $e) {
            return false;
        }
    }

    protected function getPluginAssetBaseUrl()
    {
        if (defined('WEB_PLUGIN')) {
            return rtrim(WEB_PLUGIN, '/') . '/SimpleSeoMeta';
        }

        if (defined('WEB_ROOT')) {
            return rtrim(WEB_ROOT, '/') . '/plugins/SimpleSeoMeta';
        }

        return '/plugins/SimpleSeoMeta';
    }

    protected function renderSimplePagesFallbackFields($meta)
    {
        $meta = array_merge($this->_recordMetaDefaults, (array) $meta);

        $html = '';
        $html .= '<section id="simple-seo-meta-accordion" class="simple-seo-meta-accordion">';
        $html .= '<button type="button" class="simple-seo-meta-toggle" aria-expanded="false" aria-controls="simple-seo-meta-panel"><span class="simple-seo-meta-title">' . $this->escape(__('Simple SEO Meta')) . '</span><span class="simple-seo-meta-icon" aria-hidden="true">+</span></button>';
        $html .= '<div id="simple-seo-meta-panel" class="simple-seo-meta-panel" hidden>';
        $html .= '<fieldset id="simple-seo-meta-fields" class="simple-seo-meta-fields">';
        $html .= '<legend class="sr-only visually-hidden">' . $this->escape(__('Simple SEO Meta')) . '</legend>';
        $html .= '<input type="hidden" name="simple_seo_meta_fields_present" value="1">';
        $html .= '<p class="explanation">' . $this->escape(__('Optional SEO metadata for this Simple Page. Empty fields use this plugin\'s global defaults and automatic fallbacks.')) . '</p>';

        $html .= $this->adminFieldTextFromValue('simple_seo_meta_seo_title', __('SEO title'), __('Optional. If empty, the page title and the global title template are used.'), $meta['seo_title'], 60);
        $html .= $this->adminFieldTextareaFromValue('simple_seo_meta_meta_description', __('Meta description'), __('Optional. Recommended length: about 150–160 characters.'), $meta['meta_description'], 3, 60);
        $html .= $this->adminFieldTextFromValue('simple_seo_meta_canonical_url', __('Canonical URL'), __('Optional. Leave empty to use the automatic canonical URL.'), $meta['canonical_url'], 60);

        $html .= $this->adminFieldSelectFromValue('simple_seo_meta_robots_base', __('Meta robots'), __('Use global setting to inherit the site default. Remember: none means noindex,nofollow; leaving the global setting empty means no robots meta tag, normally equivalent to index,follow.'), $this->getRecordRobotsBaseOptions(), $meta['robots_base']);
        $html .= $this->adminFieldTextFromValue('simple_seo_meta_robots_custom', __('Custom robots value'), __('Only used when Meta robots is set to Custom value. Example: noindex, nofollow, max-snippet:0'), $meta['robots_custom'], 60);

        $html .= '<div class="field"><div class="two columns alpha"><label>' . $this->escape(__('Additional robots directives')) . '</label></div><div class="inputs five columns omega">';
        $html .= $this->adminCheckboxLabelFromValue('simple_seo_meta_robots_nosnippet', 'nosnippet', $meta['robots_nosnippet']);
        $html .= $this->adminCheckboxLabelFromValue('simple_seo_meta_robots_indexifembedded', 'indexifembedded', $meta['robots_indexifembedded']);
        $html .= $this->adminCheckboxLabelFromValue('simple_seo_meta_robots_notranslate', 'notranslate', $meta['robots_notranslate']);
        $html .= $this->adminCheckboxLabelFromValue('simple_seo_meta_robots_noimageindex', 'noimageindex', $meta['robots_noimageindex']);
        $html .= $this->adminCheckboxLabelFromValue('simple_seo_meta_robots_noarchive', 'noarchive', $meta['robots_noarchive']);
        $html .= $this->adminCheckboxLabelFromValue('simple_seo_meta_robots_nocache', 'nocache', $meta['robots_nocache']);
        $html .= $this->adminCheckboxLabelFromValue('simple_seo_meta_robots_nositelinkssearchbox', 'nositelinkssearchbox', $meta['robots_nositelinkssearchbox']);
        $html .= '</div></div>';

        $html .= $this->adminFieldTextFromValue('simple_seo_meta_robots_max_snippet', __('robots: max-snippet'), __('Optional integer. 0 is equivalent to nosnippet; -1 means no limit.'), $meta['robots_max_snippet'], 10);
        $html .= $this->adminFieldSelectFromValue('simple_seo_meta_robots_max_image_preview', __('robots: max-image-preview'), __('Optional image preview limit.'), $this->getMaxImagePreviewOptions(), $meta['robots_max_image_preview']);
        $html .= $this->adminFieldTextFromValue('simple_seo_meta_robots_max_video_preview', __('robots: max-video-preview'), __('Optional integer in seconds. 0 allows at most a static image; -1 means no limit.'), $meta['robots_max_video_preview'], 10);
        $html .= $this->adminFieldTextFromValue('simple_seo_meta_robots_unavailable_after', __('robots: unavailable_after'), __('Optional date/time. Example: Wed, 31 Dec 2026 23:59:59 GMT.'), $meta['robots_unavailable_after'], 40);

        $html .= $this->adminFieldTextFromValue('simple_seo_meta_og_title', __('Open Graph title'), __('Optional. If empty, the SEO title is used.'), $meta['og_title'], 60);
        $html .= $this->adminFieldTextareaFromValue('simple_seo_meta_og_description', __('Open Graph description'), __('Optional. If empty, the meta description is used.'), $meta['og_description'], 2, 60);
        $html .= $this->adminFieldTextFromValue('simple_seo_meta_og_type', __('Open Graph type'), __('Optional. Example: website, article.'), $meta['og_type'], 30);
        $html .= $this->adminFieldTextFromValue('simple_seo_meta_og_image', __('Open Graph image URL'), __('Optional absolute or root-relative URL.'), $meta['og_image'], 60);
        $html .= $this->adminFieldTextFromValue('simple_seo_meta_og_image_alt', __('Open Graph image alt text'), __('Optional text alternative for the social preview image.'), $meta['og_image_alt'], 60);

        $html .= $this->adminFieldSelectFromValue('simple_seo_meta_twitter_card', __('Twitter/X Card type'), __('Optional. If empty, the global card type is used.'), $this->getRecordTwitterCardOptions(), $meta['twitter_card']);
        $html .= $this->adminFieldTextFromValue('simple_seo_meta_twitter_title', __('Twitter/X title'), __('Optional. If empty, the Open Graph title or SEO title is used.'), $meta['twitter_title'], 60);
        $html .= $this->adminFieldTextareaFromValue('simple_seo_meta_twitter_description', __('Twitter/X description'), __('Optional. If empty, the Open Graph description or meta description is used.'), $meta['twitter_description'], 2, 60);
        $html .= $this->adminFieldTextFromValue('simple_seo_meta_twitter_image', __('Twitter/X image URL'), __('Optional absolute or root-relative URL.'), $meta['twitter_image'], 60);
        $html .= $this->adminFieldTextFromValue('simple_seo_meta_twitter_image_alt', __('Twitter/X image alt text'), __('Optional text alternative for the Twitter/X image.'), $meta['twitter_image_alt'], 60);

        $html .= '</fieldset>';
        $html .= '</div>';
        $html .= '</section>';
        return $html;
    }

    protected function adminFieldTextFromValue($name, $label, $description, $value, $size)
    {
        $html = '<div class="field">';
        $html .= '<div class="two columns alpha"><label for="' . $this->escape($name) . '">' . $this->escape($label) . '</label></div>';
        $html .= '<div class="inputs five columns omega">';
        $html .= '<input type="text" name="' . $this->escape($name) . '" id="' . $this->escape($name) . '" value="' . $this->escape($value) . '" size="' . (int) $size . '">';
        if ($description !== '') {
            $html .= '<p class="explanation">' . $this->escape($description) . '</p>';
        }
        $html .= '</div></div>';
        return $html;
    }

    protected function adminFieldTextareaFromValue($name, $label, $description, $value, $rows, $cols)
    {
        $html = '<div class="field">';
        $html .= '<div class="two columns alpha"><label for="' . $this->escape($name) . '">' . $this->escape($label) . '</label></div>';
        $html .= '<div class="inputs five columns omega">';
        $html .= '<textarea name="' . $this->escape($name) . '" id="' . $this->escape($name) . '" rows="' . (int) $rows . '" cols="' . (int) $cols . '">' . $this->escape($value) . '</textarea>';
        if ($description !== '') {
            $html .= '<p class="explanation">' . $this->escape($description) . '</p>';
        }
        $html .= '</div></div>';
        return $html;
    }

    protected function adminFieldSelectFromValue($name, $label, $description, $options, $value)
    {
        $html = '<div class="field">';
        $html .= '<div class="two columns alpha"><label for="' . $this->escape($name) . '">' . $this->escape($label) . '</label></div>';
        $html .= '<div class="inputs five columns omega">';
        $html .= '<select name="' . $this->escape($name) . '" id="' . $this->escape($name) . '">';
        foreach ($options as $optionValue => $optionLabel) {
            $selected = ((string) $value === (string) $optionValue) ? ' selected="selected"' : '';
            $html .= '<option value="' . $this->escape($optionValue) . '"' . $selected . '>' . $this->escape($optionLabel) . '</option>';
        }
        $html .= '</select>';
        if ($description !== '') {
            $html .= '<p class="explanation">' . $this->escape($description) . '</p>';
        }
        $html .= '</div></div>';
        return $html;
    }

    protected function adminCheckboxLabelFromValue($name, $label, $value)
    {
        $checked = ((string) $value === '1') ? ' checked="checked"' : '';
        return '<label><input type="checkbox" name="' . $this->escape($name) . '" id="' . $this->escape($name) . '" value="1"' . $checked . '> ' . $this->escape($label) . '</label><br>';
    }

    protected function createMetaTable()
    {
        $db = $this->_db;
        $table = $this->getMetaTableName();
        $sql = "CREATE TABLE IF NOT EXISTS `$table` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `record_type` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
            `record_id` int(10) unsigned NOT NULL,
            `data` mediumtext COLLATE utf8_unicode_ci,
            `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `record_unique` (`record_type`, `record_id`),
            KEY `record_type` (`record_type`),
            KEY `record_id` (`record_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        $db->query($sql);
    }

    protected function dropMetaTable()
    {
        $db = $this->_db;
        $table = $this->getMetaTableName();
        $db->query("DROP TABLE IF EXISTS `$table`");
    }

    protected function getMetaTableName()
    {
        return $this->_db->prefix . 'simple_seo_meta_records';
    }

    protected function getCurrentRecordContext()
    {
        $item = $this->getCurrentRecordSafe('item');
        if ($item) {
            return array('record_type' => 'Item', 'record_id' => (int) $item->id, 'record' => $item);
        }

        $collection = $this->getCurrentRecordSafe('collection');
        if ($collection) {
            return array('record_type' => 'Collection', 'record_id' => (int) $collection->id, 'record' => $collection);
        }

        $simplePage = $this->getCurrentSimplePage();
        if ($simplePage) {
            return array('record_type' => 'SimplePagesPage', 'record_id' => (int) $simplePage->id, 'record' => $simplePage);
        }

        return null;
    }

    protected function getCurrentSimplePage()
    {
        if (!class_exists('SimplePagesPage')) {
            return false;
        }

        try {
            $request = Zend_Controller_Front::getInstance()->getRequest();
            if (!$request) {
                return false;
            }

            if ($request->getModuleName() !== 'simple-pages' || $request->getControllerName() !== 'page') {
                return false;
            }

            $pageId = (int) $request->getParam('id');
            if (!$pageId) {
                return false;
            }

            return get_db()->getTable('SimplePagesPage')->find($pageId);
        } catch (Exception $e) {
            return false;
        }
    }

    protected function getCurrentPageTitle($context = null)
    {
        if ($context && $context['record_type'] === 'SimplePagesPage') {
            return $this->normalizeText($context['record']->title);
        }

        $item = $this->getCurrentRecordSafe('item');
        if ($item) {
            return $this->normalizeText($this->recordMetadata($item, array('Dublin Core', 'Title')));
        }

        $collection = $this->getCurrentRecordSafe('collection');
        if ($collection) {
            return $this->normalizeText($this->recordMetadata($collection, array('Dublin Core', 'Title')));
        }

        return '';
    }

    protected function getCurrentPageDescription($context = null)
    {
        if ($context && $context['record_type'] === 'SimplePagesPage') {
            return $this->truncateText($this->normalizeText($context['record']->text), 220);
        }

        $item = $this->getCurrentRecordSafe('item');
        if ($item) {
            return $this->normalizeText($this->recordMetadata($item, array('Dublin Core', 'Description')));
        }

        $collection = $this->getCurrentRecordSafe('collection');
        if ($collection) {
            return $this->normalizeText($this->recordMetadata($collection, array('Dublin Core', 'Description')));
        }

        return '';
    }

    protected function getCurrentPageImageUrl($context = null)
    {
        $item = $this->getCurrentRecordSafe('item');
        if (!$item || empty($item->Files)) {
            return '';
        }

        foreach ($item->Files as $file) {
            if (isset($file->mime_type) && strpos($file->mime_type, 'image/') !== 0) {
                continue;
            }

            if (function_exists('file_display_url')) {
                return file_display_url($file, 'fullsize');
            }
        }

        return '';
    }

    protected function getCurrentPageImageAlt($context = null)
    {
        $item = $this->getCurrentRecordSafe('item');
        if (!$item || empty($item->Files)) {
            return '';
        }

        foreach ($item->Files as $file) {
            if (isset($file->mime_type) && strpos($file->mime_type, 'image/') !== 0) {
                continue;
            }

            $title = $this->recordMetadata($file, array('Dublin Core', 'Title'));
            if ($title !== '') {
                return $this->normalizeText($title);
            }
        }

        return $this->getCurrentPageTitle($context);
    }

    protected function getCurrentRecordSafe($recordVar)
    {
        try {
            return get_current_record($recordVar, false);
        } catch (Exception $e) {
            return false;
        }
    }

    protected function recordMetadata($record, $metadata)
    {
        try {
            $value = metadata($record, $metadata, array('no_escape' => true));
            if (is_array($value)) {
                $value = implode(' ', $value);
            }
            return $value;
        } catch (Exception $e) {
            return '';
        }
    }

    protected function getCanonicalUrl()
    {
        $url = $this->currentAbsoluteUrl();

        if ($this->getOptionBool('simple_seo_meta_strip_query_from_canonical')) {
            $parts = explode('?', $url, 2);
            $url = $parts[0];
        }

        return $url;
    }

    protected function currentAbsoluteUrl()
    {
        $scheme = 'http';
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
            $scheme = 'https';
        }

        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        if ($host === '') {
            return '';
        }

        return $scheme . '://' . $host . $uri;
    }

    protected function absoluteUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        $scheme = 'http';
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
            $scheme = 'https';
        }

        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        if ($host === '') {
            return $url;
        }

        if (strpos($url, '/') === 0) {
            return $scheme . '://' . $host . $url;
        }

        return $scheme . '://' . $host . '/' . $url;
    }

    protected function getSiteTitle()
    {
        $title = get_option('site_title');
        return $this->normalizeText($title);
    }

    protected function getOptionTrimmed($name, $default)
    {
        $value = get_option($name);
        if ($value === null || $value === false) {
            return $default;
        }
        return trim($value);
    }

    protected function getOptionBool($name)
    {
        return get_option($name) === '1';
    }

    protected function getPostValue($post, $name)
    {
        return isset($post[$name]) ? trim((string) $post[$name]) : '';
    }

    protected function getPostCheckbox($post, $name)
    {
        return (isset($post[$name]) && (string) $post[$name] === '1') ? '1' : '0';
    }

    protected function sanitizeAdminText($value)
    {
        return $this->normalizeText($value);
    }

    protected function sanitizeUrlLike($url)
    {
        $url = trim((string) $url);
        return preg_replace('/[\x00-\x1F\x7F\s]+/', '', $url);
    }

    protected function sanitizeRecordRobotsBase($value)
    {
        $value = trim((string) $value);
        $allowed = array_keys($this->getRecordRobotsBaseOptions());
        return in_array($value, $allowed) ? $value : 'inherit';
    }

    protected function sanitizeRecordTwitterCard($value)
    {
        $value = trim((string) $value);
        $allowed = array_keys($this->getRecordTwitterCardOptions());
        return in_array($value, $allowed) ? $value : '';
    }

    protected function sanitizeTwitterCard($value)
    {
        $value = trim((string) $value);
        $allowed = array_keys($this->getGlobalTwitterCardOptions());
        return in_array($value, $allowed) ? $value : 'summary_large_image';
    }

    protected function sanitizeMaxImagePreview($value)
    {
        $value = trim((string) $value);
        return in_array($value, array('', 'none', 'standard', 'large')) ? $value : '';
    }

    protected function sanitizeOpenGraphType($value)
    {
        $value = trim((string) $value);
        $value = preg_replace('/[^A-Za-z0-9_:\.\-]/', '', $value);
        return $value;
    }

    protected function sanitizeRobotsCustomValue($value)
    {
        $value = strtolower((string) $value);
        $value = preg_replace('/[^a-z0-9_:\-,\.\s]/', '', $value);
        $value = preg_replace('/\s*,\s*/', ', ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    protected function sanitizeIntegerLikeDirectiveValue($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^-?\d+$/', $value)) {
            return $value;
        }
        return '';
    }

    protected function sanitizeRobotsDate($value)
    {
        return trim(preg_replace('/[^a-zA-Z0-9,:\-\+\.\s]/', '', (string) $value));
    }

    protected function normalizeText($value)
    {
        $value = (string) $value;
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        $value = strip_tags($value);
        $value = preg_replace('/\s+/u', ' ', $value);
        return trim($value);
    }

    protected function truncateText($value, $length)
    {
        $value = $this->normalizeText($value);
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value, 'UTF-8') <= $length) {
                return $value;
            }
            return rtrim(mb_substr($value, 0, $length, 'UTF-8')) . '…';
        }
        if (strlen($value) <= $length) {
            return $value;
        }
        return rtrim(substr($value, 0, $length)) . '…';
    }

    protected function normalizeTwitterHandle($handle)
    {
        $handle = trim((string) $handle);
        if ($handle === '') {
            return '';
        }
        $handle = preg_replace('/[^A-Za-z0-9_@]/', '', $handle);
        if ($handle !== '' && strpos($handle, '@') !== 0) {
            $handle = '@' . $handle;
        }
        return $handle;
    }

    protected function metaName($name, $content)
    {
        return '<meta name="' . $this->escape($name) . '" content="' . $this->escape($content) . '">';
    }

    protected function metaProperty($property, $content)
    {
        return '<meta property="' . $this->escape($property) . '" content="' . $this->escape($content) . '">';
    }

    protected function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    protected function text($name, $size)
    {
        return '<input type="text" name="' . $this->escape($name) . '" id="' . $this->escape($name) . '" value="' . $this->escape(get_option($name)) . '" size="' . (int) $size . '">';
    }

    protected function textarea($name, $cols, $rows)
    {
        return '<textarea name="' . $this->escape($name) . '" id="' . $this->escape($name) . '" cols="' . (int) $cols . '" rows="' . (int) $rows . '">' . $this->escape(get_option($name)) . '</textarea>';
    }

    protected function checkbox($name)
    {
        $checked = $this->getOptionBool($name) ? ' checked="checked"' : '';
        return '<input type="checkbox" name="' . $this->escape($name) . '" id="' . $this->escape($name) . '" value="1"' . $checked . '>';
    }

    protected function select($name, $options)
    {
        $current = get_option($name);
        $html = '<select name="' . $this->escape($name) . '" id="' . $this->escape($name) . '">';
        foreach ($options as $value => $label) {
            $selected = ((string) $current === (string) $value) ? ' selected="selected"' : '';
            $html .= '<option value="' . $this->escape($value) . '"' . $selected . '>' . $this->escape($label) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }
}

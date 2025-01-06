<?php declare(strict_types=1);

namespace Group\Form\Element;

use Common\Form\Element\TraitOptionalElement;
use Laminas\Form\Element\Select;
use Laminas\View\Helper\Url;
use Omeka\Api\Manager as ApiManager;

class GroupSelect extends Select
{
    use TraitOptionalElement;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Laminas\View\Helper\Url
     */
    protected $urlHelper;

    public function getValueOptions(): array
    {
        $query = $this->getOption('query');
        if (!is_array($query)) {
            $query = [];
        }
        if (!isset($query['sort_by'])) {
            $query['sort_by'] = 'name';
        }

        $nameAsValue = $this->getOption('name_as_value', false);

        $valueOptions = [];
        $response = $this->api->search('groups', $query);
        foreach ($response->getContent() as $representation) {
            $name = $representation->name();
            $key = $nameAsValue ? $name : $representation->id();
            $valueOptions[$key] = $name;
        }

        $prependValueOptions = $this->getOption('prepend_value_options');
        if (is_array($prependValueOptions)) {
            $valueOptions = $prependValueOptions + $valueOptions;
        }
        return $valueOptions;
    }

    public function setOptions($options)
    {
        if (!empty($options['chosen'])) {
            $defaultOptions = [
                'resource_value_options' => [
                    'resource' => 'groups',
                    'query' => [],
                    'option_text_callback' => fn ($v) => $v->name(),
                ],
                'name_as_value' => true,
            ];
            if (isset($options['resource_value_options'])) {
                $options['resource_value_options'] += $defaultOptions['resource_value_options'];
            } else {
                $options['resource_value_options'] = $defaultOptions['resource_value_options'];
            }
            if (!isset($options['name_as_value'])) {
                $options['name_as_value'] = $defaultOptions['name_as_value'];
            }

            $defaultAttributes = [
                'class' => 'chosen-select',
                'data-placeholder' => 'Select groups…', // @translate
                'data-api-base-url' => $this->urlHelper->__invoke('api/default', ['resource' => 'groups']),
            ];
            $this->setAttributes($defaultAttributes);
        }

        return parent::setOptions($options);
    }

    public function setApi(ApiManager $api): self
    {
        $this->api = $api;
        return $this;
    }

    public function setUrlHelper(Url $urlHelper): self
    {
        $this->urlHelper = $urlHelper;
        return $this;
    }
}

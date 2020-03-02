<?php
/**
 *
**/
namespace FishPig\WordPress_Yoast\Plugin;

use \FishPig\WordPress\Api\Data\Entity\ViewableInterface;

class Post extends AbstractPlugin
{
	/**
	 * Yoast field mappings for Posts
	 *
	 * @const string
	**/
	const FIELD_PAGE_TITLE = '_yoast_wpseo_title';
	const FIELD_META_DESCRIPTION = '_yoast_wpseo_metadesc';
	const FIELD_META_KEYWORDS = '_yoast_wpseo_metakeywords';
	const FIELD_NOINDEX = '_yoast_wpseo_meta-robots-noindex';
	const FIELD_NOFOLLOW = '_yoast_wpseo_meta-robots-nofollow';
	const FIELD_ROBOTS_ADVANCED = '_yoast_wpseo_meta-robots-adv';
	const FIELD_CANONICAL = '_yoast_wpseo_canonical';
	
	/**
	 * Get the Yoast Page title
	 *
	 * @param ViewableInterface $object
	 * @return string|null
	**/
	protected function _aroundGetPageTitle(ViewableInterface $object)
	{
		$rewriteData = [];

		if (($value = trim($object->getMetaValue(self::FIELD_PAGE_TITLE))) !== '') {
			return $value;
			$rewriteData = $this->getRewriteData(array('title' => $value));
		}

		return $this->_rewriteString(
			$this->_getPageTitleFormat($object->getPostType()),
			$rewriteData
		);
	}
	
	/**
	 * Get the Yoast meta description
	 *
	 * @param ViewableInterface $object
	 * @return string|null
	**/
	protected function _aroundGetMetaDescription(ViewableInterface $object)
	{
		if (($value = trim($object->getMetaValue(self::FIELD_META_DESCRIPTION))) !== '') {
			return $value;
		}
		
		return $this->_rewriteString(
			$this->_getMetaDescriptionFormat($object->getPostType())
		);
	}
	
	/**
	 * Get the Yoast meta keywords
	 *
	 * @param ViewableInterface $object
	 * @return string|null
	**/
	protected function _aroundGetMetaKeywords(ViewableInterface $object)
	{
		if (($value = trim($object->getMetaValue(self::FIELD_META_KEYWORDS))) !== '') {
			return $value;
		}

		return $this->_rewriteString(
			$this->_getMetaKeywordsFormat($object->getPostType())
		);
	}
	
	/**
	 * Get the Yoast robots tag value
	 *
	 * @param ViewableInterface $object
	 * @return string|null
	**/
	protected function _aroundGetRobots(ViewableInterface $object)
	{
		$robots = ['index' => 'index', 'follow' => 'follow'];

		if ($this->_isNoindex($object->getPostType())) {
			$robots['index'] = 'noindex';
		}

		switch((int)$object->getMetaValue(self::FIELD_NOINDEX)) {
			case 1:  $robots['index'] = 'noindex';   break;
			case 2:  $robots['index'] = 'index';       break;
		}

		if ((int)$object->getMetaValue(self::FIELD_NOFOLLOW) === 1) {
			$robots['follow'] = 'nofollow';
		}

		if (($advancedRobots = trim($object->getMetaValue(self::FIELD_ROBOTS_ADVANCED))) !== '') {
			if ($advancedRobots !== 'none') {
				$robots['advanced'] = $advancedRobots;
			}
		}
	
		return $robots;
	}
	
	/**
	 * Get the Yoast canonical URL
	 *
	 * @param ViewableInterface $object
	 * @return string|null
	**/
	protected function _aroundGetCanonicalUrl(ViewableInterface $object)
	{
		if ($value = $object->getMetaValue(self::FIELD_CANONICAL)) {
			return $value;
		}

		return null;
	}
}

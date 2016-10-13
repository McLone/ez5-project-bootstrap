<?php

/**
 * Classe abstraite utilitaire de récupération de contenu eZ
 * v1.0
 * 
 * 
 */

namespace AppBundle\EzRepository;

use AppBundle\EzRepository\Exception\ContentNotFoundException;
use AppBundle\EzRepository\Exception\LocationNotFoundException;
use eZ\Publish\Core\Repository\Repository as EzOriginalRepository;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Query\SortClause;
use eZ\Publish\API\Repository\Values\Content\Search\SearchResult;
use eZ\Publish\Core\MVC\ConfigResolverInterface;

abstract class Repository
{
    /** @var  EzOriginalRepository */
    protected $repository;
    /** @var  ConfigResolverInterface */
    protected $configResolver;

    /**
     * @param EzOriginalRepository $repository
     * @param ConfigResolverInterface $configResolver
     */
    public function __construct($repository, ConfigResolverInterface $configResolver)
    {
        $this->repository = $repository;
        $this->configResolver = $configResolver;
    }

    /**
     * @return int
     */
    public function getRootLocationId()
    {
        $rootLocationId = (int)$this->configResolver->getParameter('content.tree_root.location_id');
        return $rootLocationId;
    }

    /**
     * @return Location
     */
    public function fetchRootLocation()
    {
        return $this->repository->getLocationService()->loadLocation($this->getRootLocationId());
    }

    protected function getSortClausesFromLocation(Location $location)
    {
        $sortClause = null;

        $direction = null;
        if ($location->sortOrder == Location::SORT_ORDER_ASC) {
            $direction = Query::SORT_ASC;
        } else {
            $direction = Query::SORT_DESC;
        }

        switch ($location->sortField) {
            case Location::SORT_FIELD_CONTENTOBJECT_ID:
                $sortClause = new SortClause\ContentId($direction);
                break;
            case Location::SORT_FIELD_DEPTH:
                $sortClause = new SortClause\Location\Depth($direction);
                break;
            case Location::SORT_FIELD_MODIFIED:
                $sortClause = new SortClause\DateModified($direction);
                break;
            case Location::SORT_FIELD_NAME:
                $sortClause = new SortClause\ContentName($direction);
                break;
            case Location::SORT_FIELD_NODE_ID:
                $sortClause = new SortClause\Location\Id($direction);
                break;
            case Location::SORT_FIELD_PATH:
                $sortClause = new SortClause\Location\Path($direction);
                break;
            case Location::SORT_FIELD_SECTION:
                $sortClause = new SortClause\SectionName($direction); //qui aurait aussi pu être section_identifier, mais je trouvais ça plus pertinent  -Marc
                break;
            case Location::SORT_FIELD_PUBLISHED:
                $sortClause = new SortClause\DatePublished($direction);
                break;
            case Location::SORT_FIELD_PRIORITY:
                $sortClause = new SortClause\Location\Priority($direction);
                break;
            case Location::SORT_FIELD_MODIFIED_SUBNODE:
            case Location::SORT_FIELD_CLASS_IDENTIFIER:
            case Location::SORT_FIELD_CLASS_NAME:
                //A moins que je ne l'ai loupé, il s'agit d'ordre impossible avec une Query ?
                return array();
                break;
        }

        return array($sortClause);
    }

    /**
     * @param Location $location
     * @param $contentTypeIdentifierList
     * @param $limit
     * @param $offset
     * @param $sortClauseList
     * @param $additionalCriterionList
     * @return LocationQuery
     */
    protected function getDirectChildrenLocationQuery(Location $location, $contentTypeIdentifierList = null, $limit = 0, $offset = 0, $sortClauseList = null, $additionalCriterionList = array())
    {
        $criterionList = array_merge(
            $additionalCriterionList,
            array(
                new Criterion\ParentLocationId($location->id),
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
            )
        );


        if(!empty($contentTypeIdentifierList)) {
            $criterionList[] = new Criterion\ContentTypeIdentifier($contentTypeIdentifierList);
        }

        $query = new LocationQuery();
        $query->criterion = new Criterion\LogicalAnd($criterionList);

        $query->sortClauses = $sortClauseList === null ? $this->getSortClausesFromLocation($location) : $sortClauseList;

        if ($limit) {
            $query->limit = $limit;
        }
        if ($offset) {
            $query->offset = $offset;
        }

        return $query;
    }

    /**
     * @param Location $location
     * @param null|string|string[] $contentTypeIdentifierList
     * @param int $limit
     * @param int $offset
     * @param $sortClauseList
     * @param array $additionalCriterionList
     * @return LocationQuery
     */
    protected function getAnyDepthChildrenLocationQuery(Location $location, $contentTypeIdentifierList = null, $limit = 0, $offset = 0, $sortClauseList = null, $additionalCriterionList = array())
    {
        $criterionList = array_merge(
            $additionalCriterionList,
            array(
                new Criterion\Subtree($location->pathString),
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
            )
        );


        if(!empty($contentTypeIdentifierList)) {
            $criterionList[] = new Criterion\ContentTypeIdentifier($contentTypeIdentifierList);
        }

        $query = new LocationQuery();
        $query->criterion = new Criterion\LogicalAnd($criterionList);

        $query->sortClauses = $sortClauseList === null ? $this->getSortClausesFromLocation($location) : $sortClauseList;

        if ($limit) {
            $query->limit = $limit;
        }
        if ($offset) {
            $query->offset = $offset;
            return $query;
        }

        return $query;
    }

    /**
     * @param SearchResult $searchResult
     * @return Location[]
     */
    protected function fetchLocationListFromSearchResult($searchResult)
    {
        $locationList = array();
        foreach ($searchResult->searchHits as $searchHit) {
            /** @var Location $location */
            $location = $searchHit->valueObject;
            $locationList[] = $this->repository->getLocationService()->loadLocation($location->id);
        }
        return $locationList;
    }

    protected function fetchContentListFromSearchResult($searchResult)
    {
        $contentList = array();
        foreach ($searchResult->searchHits as $searchHit) {
            /** @var Location $location */
            $location = $searchHit->valueObject;
            $contentList[] = $this->repository->getContentService()->loadContent($location->contentId);
        }
        return $contentList;
    }

    /**
     * @param Location $location
     * @param string|array $contentTypeIdentifierList
     * @param Criterion[] $additionalCriterionList
     * @return \eZ\Publish\API\Repository\Values\Content\Location[]
     */
    protected function fetchDirectChildrenCount(Location $location, $contentTypeIdentifierList = null, $additionalCriterionList = array())
    {
        $query = $this->getDirectChildrenLocationQuery($location, $contentTypeIdentifierList, $additionalCriterionList);

        $searchResult = $this->repository->getSearchService()->findLocations($query);

        return $searchResult->totalCount;
    }

    /**
     * @param Location $location
     * @param string|array $contentTypeIdentifierList
     * @param int $limit
     * @param int $offset
     * @param SortClause[] $sortClauseList
     * @param Criterion[] $additionalCriterionList
     * @return \eZ\Publish\API\Repository\Values\Content\Location[]
     */
    protected function fetchDirectChildrenLocationList(Location $location, $contentTypeIdentifierList = null, $limit = 0, $offset = 0, $sortClauseList = null, $additionalCriterionList = array())
    {
        $query = $this->getDirectChildrenLocationQuery($location, $contentTypeIdentifierList, $limit, $offset, $sortClauseList, $additionalCriterionList);

        return $this->fetchLocationListFromSearchResult($this->repository->getSearchService()->findLocations($query));
    }

    /**
     * @param Location $location
     * @param string|array $contentTypeIdentifierList
     * @param int $limit
     * @param int $offset
     * @param SortClause[] $sortClauseList
     * @param Criterion[] $additionalCriterionList
     * @return \eZ\Publish\API\Repository\Values\Content\Content[]
     */
    protected function fetchDirectChildrenContentList(Location $location, $contentTypeIdentifierList = null, $limit = 0, $offset = 0, $sortClauseList = null, $additionalCriterionList = array())
    {
        $query = $this->getDirectChildrenLocationQuery($location, $contentTypeIdentifierList, $limit, $offset, $sortClauseList, $additionalCriterionList);

        return $this->fetchContentListFromSearchResult($this->repository->getSearchService()->findLocations($query));
    }

    /**
     * @param Location $location
     * @param string|array $contentTypeIdentifierList
     * @param Criterion[] $additionalCriterionList
     * @return \eZ\Publish\API\Repository\Values\Content\Location[]
     */
    protected function fetchAnyDepthChildrenCount(Location $location, $contentTypeIdentifierList = null, $additionalCriterionList = array())
    {
        $query = $this->getAnyDepthChildrenLocationQuery($location, $contentTypeIdentifierList, $additionalCriterionList);

        $searchResult = $this->repository->getSearchService()->findLocations($query);

        return $searchResult->totalCount;
    }

    /**
     * @param Location $location
     * @param string|array $contentTypeIdentifierList
     * @param int $limit
     * @param int $offset
     * @param SortClause[] $sortClauseList
     * @param Criterion[] $additionalCriterionList
     * @return \eZ\Publish\API\Repository\Values\Content\Location[]
     */
    protected function fetchAnyDepthChildrenLocationList(Location $location, $contentTypeIdentifierList = null, $limit = 0, $offset = 0, $sortClauseList = null, $additionalCriterionList = array())
    {
        $query = $this->getAnyDepthChildrenLocationQuery($location, $contentTypeIdentifierList, $limit, $offset, $sortClauseList, $additionalCriterionList);

        $searchResult = $this->repository->getSearchService()->findLocations($query);

        return $this->fetchLocationListFromSearchResult($searchResult);
    }

    /**
     * @param Location $location
     * @param string|array $contentTypeIdentifierList
     * @param int $limit
     * @param int $offset
     * @param SortClause[] $sortClauseList
     * @param Criterion[] $additionalCriterionList
     * @return \eZ\Publish\API\Repository\Values\Content\Location[]
     */
    protected function fetchAnyDepthChildrenContentList(Location $location, $contentTypeIdentifierList = null, $limit = 0, $offset = 0, $sortClauseList = null, $additionalCriterionList = array())
    {
        $query = $this->getAnyDepthChildrenLocationQuery($location, $contentTypeIdentifierList, $limit, $offset, $sortClauseList, $additionalCriterionList);

        $searchResult = $this->repository->getSearchService()->findLocations($query);

        return $this->fetchContentListFromSearchResult($searchResult);
    }

    /**
     * @param SearchResult $searchResult
     * @return Location
     * @throws LocationNotFoundException
     */
    protected function fetchSingleLocationFromSearchResult($searchResult)
    {
        foreach ($searchResult->searchHits as $searchHit) {
            /** @var Location $location */
            $location = $searchHit->valueObject;
            return $this->repository->getLocationService()->loadLocation($location->id);
        }

        throw new LocationNotFoundException();
    }

    /**
     * @param SearchResult $searchResult
     * @return Content
     * @throws ContentNotFoundException
     */
    protected function fetchSingleContentFromSearchResult($searchResult)
    {
        foreach ($searchResult->searchHits as $searchHit) {
            /** @var Location $location */
            $location = $searchHit->valueObject;
            return $this->repository->getContentService()->loadContent($location->contentId);
        }

        throw new ContentNotFoundException();
    }

    protected function fetchSingleDirectChildrenLocation(Location $location, $contentTypeIdentifierList = null, $sortClauseList = null, $additionalCriterionList = array())
    {
        $query = $this->getDirectChildrenLocationQuery($location, $contentTypeIdentifierList, 1, 0, $sortClauseList, $additionalCriterionList);

        return $this->fetchSingleLocationFromSearchResult($this->repository->getSearchService()->findLocations($query));
    }

    protected function fetchSingleDirectChildrenContent(Location $location, $contentTypeIdentifierList = null, $sortClauseList = null, $additionalCriterionList = array())
    {
        $query = $this->getDirectChildrenLocationQuery($location, $contentTypeIdentifierList, 1, 0, $sortClauseList, $additionalCriterionList);

        return $this->fetchSingleContentFromSearchResult($this->repository->getSearchService()->findLocations($query));
    }

    protected function fetchSingleAnyDepthChildrenLocation(Location $location, $contentTypeIdentifierList = null, $sortClauseList = null, $additionalCriterionList = array())
    {
        $query = $this->getAnyDepthChildrenLocationQuery($location, $contentTypeIdentifierList, 1, 0, $sortClauseList, $additionalCriterionList);

        return $this->fetchSingleLocationFromSearchResult($this->repository->getSearchService()->findLocations($query));
    }

    protected function fetchSingleAnyDepthChildrenContent(Location $location, $contentTypeIdentifierList = null, $sortClauseList = null, $additionalCriterionList = array())
    {
        $query = $this->getAnyDepthChildrenLocationQuery($location, $contentTypeIdentifierList, 0, $sortClauseList, $additionalCriterionList, 1);

        return $this->fetchSingleContentFromSearchResult($this->repository->getSearchService()->findLocations($query));
    }
}
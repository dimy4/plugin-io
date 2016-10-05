<?php //strict

namespace LayoutCore\Extensions\Functions;

use Plenty\Plugin\Application;
use Plenty\Modules\Item\DataLayer\Models\Record;
use Plenty\Modules\Item\DataLayer\Models\RecordList;
use Plenty\Modules\Item\DataLayer\Contracts\ItemDataLayerRepositoryContract;
use LayoutCore\Builder\Item\ItemColumnBuilder;
use LayoutCore\Builder\Item\ItemFilterBuilder;
use LayoutCore\Builder\Item\ItemParamsBuilder;
use LayoutCore\Builder\Item\Fields\VariationBaseFields;
use LayoutCore\Builder\Item\Fields\VariationRetailPriceFields;
use LayoutCore\Builder\Item\Params\ItemColumnsParams;
use LayoutCore\Constants\Language;
use LayoutCore\Extensions\AbstractFunction;

/**
 * Class GetBasePrice
 * @package LayoutCore\Extensions\Functions
 */
class GetBasePrice extends AbstractFunction
{
	/**
	 * @var Application
	 */
	private $app;
	/**
	 * @var ItemDataLayerRepositoryContract
	 */
	private $itemRepository;
	/**
	 * @var ItemColumnBuilder
	 */
	private $columnBuilder;
	/**
	 * @var ItemFilterBuilder
	 */
	private $filterBuilder;
	/**
	 * @var ItemParamsBuilder
	 */
	private $paramsBuilder;
    
    /**
     * GetBasePrice constructor.
     * @param Application $app
     * @param ItemDataLayerRepositoryContract $itemRepository
     * @param ItemColumnBuilder $columnBuilder
     * @param ItemFilterBuilder $filterBuilder
     * @param ItemParamsBuilder $paramsBuilder
     */
	public function __construct(
		Application $app,
		ItemDataLayerRepositoryContract $itemRepository,
		ItemColumnBuilder $columnBuilder,
		ItemFilterBuilder $filterBuilder,
		ItemParamsBuilder $paramsBuilder
	)
	{
		parent::__construct();
		$this->app            = $app;
		$this->itemRepository = $itemRepository;
		$this->columnBuilder  = $columnBuilder;
		$this->filterBuilder  = $filterBuilder;
		$this->paramsBuilder  = $paramsBuilder;
	}
    
    /**
     * return available filter methods
     * @return array
     */
	public function getFunctions():array
	{
		return [
			"getBasePrice" => "getBasePrice"
		];
	}
    
    /**
     * get base price for specified variation
     * @param int $variationId
     * @return array
     */
	public function getBasePrice(int $variationId):array
	{
		$columns = $this->columnBuilder
			->withVariationBase([
				                    VariationBaseFields::CONTENT,
				                    VariationBaseFields::UNIT_ID
			                    ])
			->withVariationRetailPrice([
				                           VariationRetailPriceFields::PRICE
			                           ])
			->build();

		$filter = $this->filterBuilder
			->variationHasId([$variationId])
			->build();

		// set params
		// TODO: make current language global
		$params = $this->paramsBuilder
			->withParam(ItemColumnsParams::LANGUAGE, Language::DE)
			->withParam(ItemColumnsParams::PLENTY_ID, $this->app->getPlentyId())
			->build();

		$variation = $this->itemRepository->search(
			$columns,
			$filter,
			$params
		)->current();

		$price = $variation->variationRetailPrice->price;
		$lot   = (int)$variation->variationBase->content;
		$unit  = $variation->variationBase->unitId;

		$bp_lot  = 1;
		$bp_unit = $unit;
		$factor  = 1.0;

		if((float)$lot <= 0)
		{
			$lot = 1;
		}

		if($unit == 'LTR' || $unit == 'KGM')
		{
			$bp_lot = 1;
		}
		elseif($unit == 'GRM' || $unit == 'MLT')
		{
			if($lot <= 250)
			{
				$bp_lot = 100;
			}
			else
			{
				$factor  = 1000.0;
				$bp_lot  = 1;
				$bp_unit = $unit == 'GRM' ? 'KGM' : 'LTR';
			}
		}
		elseif($unit == 'CMK')
		{
			if($lot <= 2500)
			{
				$bp_lot = 10000;
			}
			else
			{
				$factor  = 10000.0;
				$bp_lot  = 1;
				$bp_unit = 'MTK';
			}
		}
		else
		{
			$bp_lot = 1;
		}

		return [
			"lot"   => $bp_lot,
			"price" => $price * $factor * ($bp_lot / $lot),
			"unit"  => $bp_unit
		];
	}
}

<?php

namespace IlBronza\Schedules\Helpers\Applicators;

use IlBronza\MeasurementUnits\BaseMeasurementUnitHelpers\BaseMeasurementUnitHelper;
use IlBronza\MeasurementUnits\Models\MeasurementUnit;
use IlBronza\Schedules\Models\Schedule;

abstract class ScheduleLimitsCalculatorHelper
{
	public mixed $limitValue;
	public mixed $validity;
	public MeasurementUnit $measurementUnit;

	abstract public function calculateScheduleLimit() : mixed;
	abstract public function extractLimitValue(Schedule $schedule) : mixed;

	public function setLimitValue($limitValue)
	{
		$this->limitValue = $limitValue;
	}

	public function getLimitValue() : mixed
	{
		return $this->limitValue;
	}

	public function setValidity($validity) : mixed
	{
		return $this->validity = $validity;
	}

	public function getValidity() : mixed
	{
		return $this->validity;
	}

	public function setMeasurementUnit(MeasurementUnit $measurementUnit)
	{
		$this->measurementUnit = $measurementUnit;
	}

	public function getMeasurementUnit() : MeasurementUnit
	{
		return $this->measurementUnit;
	}

    public function getMeasurementUnitHelper() : BaseMeasurementUnitHelper
    {
        return $this->getMeasurementUnit()->getHelper();
    }

	public function setScheduleType(Scheduletype $scheduleType)
	{
		$this->scheduleType = $scheduleType;
	}

	public static function calculateFromSchedule(Schedule $schedule)
	{
		if(is_null($schedule->getType()->getValidity()))
			return null;

		$helper = new static();

		$helper->setLimitValue(
			$helper->extractLimitValue($schedule)
		);

		$helper->setMeasurementUnit(
			$schedule->getMeasurementUnit()
		);

		$helper->setValidity(
			$schedule->getType()->getValidity()
		);

		return $helper->calculateScheduleLimit();
	}
}











	// public function calculateDeadlineValue(mixed $startingValue)
	// {
	//     $measurementUnit = $this->getMeasurementUnit();

	//     dd($measurementUnit);

	//     return $measurementUnit->getDeadlineValue(
	//         $startingValue,
	//         $this->getValidity()
	//     );
	// }

	// public function calculateStartingValue(mixed $deadlineValue)
	// {
	//     $measurementUnit = $this->getMeasurementUnit();

	//     return $measurementUnit->getStartingValue(
	//         $deadlineValue,
	//         $this->getValidity()
	//     );
	// }















	// public function getDeadlineValue($initialValue, $validity) : mixed
	// {
	//     $this->getHelper()->getDeadlineValue($initialValue);


	//     // $measurementUnitHelper = $this->getHelper();

	//     // $deadlineValue = $measurementUnitHelper->getDeadlineValue(
	//     //     $this->convertToBaseUnitValue($initialValue),
	//     //     $this->convertToBaseUnitValue($validity)
	//     // );

	//     // return $this->getFromBaseUnitValue($deadlineValue);
	// }

	// public function getStartingValue($deadlineValue, $validity) : mixed
	// {
	//     $measurementUnitHelper = $this->getHelper();

	//     $deadlineValue = $measurementUnitHelper->getStartingValue(
	//         $this->convertToBaseUnitValue($deadlineValue),
	//         $this->convertToBaseUnitValue($validity)
	//     );

	//     return $this->getFromBaseUnitValue($deadlineValue);
	// }

	// public function getBeforeValue($deadlineValue, $validity) : mixed
	// {
	//     dd('use getStartingValue');
	//     // $measurementUnitHelper = $this->getHelper();

	//     // $deadlineValue = $measurementUnitHelper->getBeforeValue(
	//     //     $this->convertToBaseUnitValue($deadlineValue),
	//     //     $this->convertToBaseUnitValue($before)
	//     // );

	//     // return $this->getFromBaseUnitValue($deadlineValue);
	// }


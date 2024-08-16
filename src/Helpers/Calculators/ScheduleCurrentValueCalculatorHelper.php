<?php

namespace IlBronza\Schedules\Helpers\Calculators;

use IlBronza\Schedules\Helpers\Applicators\ScheduleApplicatorHelperGettersSettersTraits;
use IlBronza\Schedules\Models\Schedule;

use IlBronza\Schedules\Models\Type;

use function get_class;

class ScheduleCurrentValueCalculatorHelper
{
	public Schedule $schedule;
	public Type $type;

	use ScheduleApplicatorHelperGettersSettersTraits;
	/**
	 * this function get the current schedule value
	 * to be used to calculate the remaining time
	 * or the completion percentage
	 *
	 * @param  Schedule  $schedule
	 *
	 * @return mixed
	 */
	static function getCurrentValueFromSchedule(Schedule $schedule)
	{
		// dd('cossa serve questo');

		$helper = new static();
		$helper->setSchedule($schedule);
		$helper->setType($schedule->getType());
		$helper->setModel($schedule->getSchedulable());

		return $helper->getModelScheduleCurrentValue();
	}

	public function getModelScheduleCurrentValue() : mixed
	{
		$valueGetterMethod = $this->getModelSchedulesCurrentValueGetterMethod();

		return $this->getModel()->{$valueGetterMethod}();
	}


}
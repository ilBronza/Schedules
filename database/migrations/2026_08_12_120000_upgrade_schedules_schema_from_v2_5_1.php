<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up() : void
	{
		$schedules = config('schedules.models.schedule.table');
		$types = config('schedules.models.type.table');
		$typeNotifications = config('schedules.models.typeNotification.table');
		$scheduledNotifications = config('schedules.models.scheduledNotification.table');

		if(Schema::hasColumn($schedules, 'user_id'))
			$this->dropColumnWithForeignKeys($schedules, 'user_id');

		$this->widenNullableForeignString($schedules, 'type_id', $types);
		$this->widenNullableString($schedules, 'schedulable_id');

		$this->widenNullableForeignString($typeNotifications, 'type_id', $types);

		$this->widenNullableForeignString($scheduledNotifications, 'type_notification_id', $typeNotifications);
	}

	public function down() : void
	{
		$schedules = config('schedules.models.schedule.table');
		$types = config('schedules.models.type.table');
		$typeNotifications = config('schedules.models.typeNotification.table');
		$scheduledNotifications = config('schedules.models.scheduledNotification.table');

		Schema::table($schedules, function(Blueprint $table)
		{
			$table->unsignedBigInteger('user_id')->nullable();
			$table->foreign('user_id')->references('id')->on('users');
		});

		$this->narrowNullableForeignUuid($schedules, 'type_id', $types);
		$this->narrowNullableUuid($schedules, 'schedulable_id');

		$this->narrowNullableForeignUuid($typeNotifications, 'type_id', $types);

		$this->narrowNullableForeignUuid($scheduledNotifications, 'type_notification_id', $typeNotifications);
	}

	// Le foreign key vengono lette dallo schema: nei database v2.5.1 il nome può non seguire la convenzione o mancare del tutto
	protected function dropForeignKeys(string $table, string $column) : void
	{
		foreach(Schema::getForeignKeys($table) as $foreignKey)
		{
			if(! in_array($column, (array) $foreignKey['columns']))
				continue;

			Schema::table($table, function(Blueprint $blueprint) use ($foreignKey)
			{
				$blueprint->dropForeign($foreignKey['name']);
			});
		}
	}

	protected function dropColumnWithForeignKeys(string $table, string $column) : void
	{
		$this->dropForeignKeys($table, $column);

		Schema::table($table, function(Blueprint $blueprint) use ($column)
		{
			$blueprint->dropColumn($column);
		});
	}

	protected function widenNullableForeignString(string $table, string $column, string $foreignTable) : void
	{
		$this->dropForeignKeys($table, $column);

		Schema::table($table, function(Blueprint $blueprint) use ($column)
		{
			$blueprint->string($column)->nullable()->change();
		});

		Schema::table($table, function(Blueprint $blueprint) use ($column, $foreignTable)
		{
			$blueprint->foreign($column)->references('id')->on($foreignTable);
		});
	}

	protected function widenNullableString(string $table, string $column) : void
	{
		Schema::table($table, function(Blueprint $blueprint) use ($column)
		{
			$blueprint->string($column)->nullable()->change();
		});
	}

	protected function narrowNullableForeignUuid(string $table, string $column, string $foreignTable) : void
	{
		$this->dropForeignKeys($table, $column);

		Schema::table($table, function(Blueprint $blueprint) use ($column)
		{
			$blueprint->uuid($column)->nullable()->change();
		});

		Schema::table($table, function(Blueprint $blueprint) use ($column, $foreignTable)
		{
			$blueprint->foreign($column)->references('id')->on($foreignTable);
		});
	}

	protected function narrowNullableUuid(string $table, string $column) : void
	{
		Schema::table($table, function(Blueprint $blueprint) use ($column)
		{
			$blueprint->uuid($column)->nullable()->change();
		});
	}
};

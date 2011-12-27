<?php namespace Laravel\Database\Grammars;

use Laravel\Database\Query;
use Laravel\Database\Expression;

class Grammar {

	/**
	 * The keyword identifier for the database system.
	 *
	 * @var string
	 */
	protected $wrapper = '"';

	/**
	 * All of the query componenets in the order they should be built.
	 *
	 * @var array
	 */
	protected $components = array(
		'aggregate', 'selects', 'from', 'joins',
		'wheres', 'orderings', 'limit', 'offset',
	);

	/**
	 * Compile a SQL SELECT statement from a Query instance.
	 *
	 * @param  Query   $query
	 * @return string
	 */
	final public function select(Query $query)
	{
		$sql = array();

		// Each portion of the statement is compiled by a function corresponding
		// to an item in the components array. This lets us to keep the creation
		// of the query very granular, and allows for the flexible customization
		// of the query building process by each database system's grammar.
		//
		// Note that each component also corresponds to a public property on the
		// query instance, allowing us to pass the appropriate data into each of
		// the compiler functions.
		foreach ($this->components as $component)
		{
			if ( ! is_null($query->$component))
			{
				$sql[] = call_user_func(array($this, $component), $query);
			}
		}

		// Once all of the clauses have been compiled, we can join them all as
		// one statement. Any segments that are null or an empty string will
		// be removed from the array of clauses before they are imploded.
		return implode(' ', array_filter($sql, function($value)
		{
			return (string) $value !== '';
		}));
	}

	/**
	 * Compile the SELECT clause for a query.
	 *
	 * @param  Query   $query
	 * @return string
	 */
	protected function selects(Query $query)
	{
		$select = ($query->distinct) ? 'SELECT DISTINCT ' : 'SELECT ';

		return $select.$this->columnize($query->selects);
	}

	/**
	 * Compile an aggregating SELECT clause for a query.
	 *
	 * @param  Query   $query
	 * @return string
	 */
	protected function aggregate(Query $query)
	{
		$column = $this->wrap($query->aggregate['column']);

		return 'SELECT '.$query->aggregate['aggregator'].'('.$column.')';
	}

	/**
	 * Compile the FROM clause for a query.
	 *
	 * @param  Query   $query
	 * @return string
	 */
	protected function from(Query $query)
	{
		return 'FROM '.$this->wrap($query->from);
	}

	/**
	 * Compile the JOIN clauses for a query.
	 *
	 * @param  Query   $query
	 * @return string
	 */
	protected function joins(Query $query)
	{
		// We need to iterate through each JOIN clause that is attached to the
		// query an translate it into SQL. The table and the columns will be
		// wrapped in identifiers to avoid naming collisions.
		//
		// Once all of the JOINs have been compiled, we can concatenate them
		// together using a single space, which should give us the complete
		// set of joins in valid SQL that can appended to the query.
		foreach ($query->joins as $join)
		{
			$table = $this->wrap($join['table']);

			$column1 = $this->wrap($join['column1']);

			$column2 = $this->wrap($join['column2']);

			$sql[] = "{$join['type']} JOIN {$table} ON {$column1} {$join['operator']} {$column2}";
		}

		return implode(' ', $sql);
	}

	/**
	 * Compile the WHERE clause for a query.
	 *
	 * @param  Query   $query
	 * @return string
	 */
	final protected function wheres(Query $query)
	{
		// Each WHERE clause array has a "type" that is assigned by the query
		// builder, and each type has its own compiler function. We will call
		// the appropriate compiler for each where clause in the query.
		//
		// Keeping each particular where clause in its own "compiler" allows
		// us to keep the query generation process very granular, making it
		// easier to customize derived grammars for other databases.
		foreach ($query->wheres as $where)
		{
			$sql[] = $where['connector'].' '.$this->{$where['type']}($where);
		}

		if (isset($sql)) return implode(' ', array_merge(array('WHERE 1 = 1'), $sql));
	}

	/**
	 * Compile a simple WHERE clause.
	 *
	 * @param  array   $where
	 * @return string
	 */
	protected function where($where)
	{
		$parameter = $this->parameter($where['value']);

		return $this->wrap($where['column']).' '.$where['operator'].' '.$parameter;
	}

	/**
	 * Compile a WHERE IN clause.
	 *
	 * @param  array   $where
	 * @return string
	 */
	protected function where_in($where)
	{
		$parameters = $this->parameterize($where['values']);

		return $this->wrap($where['column']).' IN ('.$parameters.')';
	}

	/**
	 * Compile a WHERE NOT IN clause.
	 *
	 * @param  array   $where
	 * @return string
	 */
	protected function where_not_in($where)
	{
		$parameters = $this->parameterize($where['values']);

		return $this->wrap($where['column']).' NOT IN ('.$parameters.')';
	}

	/**
	 * Compile a WHERE NULL clause.
	 *
	 * @param  array   $where
	 * @return string
	 */
	protected function where_null($where)
	{
		return $this->wrap($where['column']).' IS NULL';
	}

	/**
	 * Compile a WHERE NULL clause.
	 *
	 * @param  array   $where
	 * @return string
	 */
	protected function where_not_null($where)
	{
		return $this->wrap($where['column']).' IS NOT NULL';
	}

	/**
	 * Compile a raw WHERE clause.
	 *
	 * @param  string  $where
	 * @return string
	 */
	final protected function where_raw($where)
	{
		return $where;
	}

	/**
	 * Compile the ORDER BY clause for a query.
	 *
	 * @param  Query   $query
	 * @return string
	 */
	protected function orderings(Query $query)
	{
		// To generate the list of query orderings, we will first make an array
		// of the columns and directions on which the query should be ordered.
		// Once we have an array, we can comma-delimit it and append it to
		// the "ORDER BY" clause to get the valid SQL for the query.
		//
		// All of the columns will be wrapped in keyword identifiers to avoid
		// any naming collisions with the database system. The direction of
		// the order is upper-cased strictly for syntax consistency.
		foreach ($query->orderings as $ordering)
		{
			$direction = strtoupper($ordering['direction']);

			$sql[] = $this->wrap($ordering['column']).' '.$direction;
		}

		return 'ORDER BY '.implode(', ', $sql);
	}

	/**
	 * Compile the LIMIT clause for a query.
	 *
	 * @param  Query   $query
	 * @return string
	 */
	protected function limit(Query $query)
	{
		return 'LIMIT '.$query->limit;
	}

	/**
	 * Compile the OFFSET clause for a query.
	 *
	 * @param  Query   $query
	 * @return string
	 */
	protected function offset(Query $query)
	{
		return 'OFFSET '.$query->offset;
	}

	/**
	 * Compile a SQL INSERT statment from a Query instance.
	 *
	 * This method handles the compilation of single row inserts and batch inserts.
	 *
	 * @param  Query   $query
	 * @param  array   $values
	 * @return string
	 */
	public function insert(Query $query, $values)
	{
		$table = $this->wrap($query->from);

		// Force every insert to be treated like a batch insert. This simply makes
		// creating the SQL syntax a little easier on us since we can always treat
		// the values as if it is an array containing multiple inserts.
		if ( ! is_array(reset($values))) $values = array($values);

		// Since we only care about the column names, we can pass any of the insert
		// arrays into the "columnize" method. The columns should be the same for
		// every insert to the table so we can just use the first record.
		$columns = $this->columnize(array_keys(reset($values)));

		// Build the list of parameter place-holders of values bound to the query.
		// Each insert should have the same number of bound paramters, so we can
		// just use the first array of values.
		$parameters = $this->parameterize(reset($values));

		$parameters = implode(', ', array_fill(0, count($values), "($parameters)"));

		return "INSERT INTO {$table} ({$columns}) VALUES {$parameters}";
	}

	/**
	 * Compile a SQL UPDATE statment from a Query instance.
	 *
	 * @param  Query   $query
	 * @param  array   $values
	 * @return string
	 */
	public function update(Query $query, $values)
	{
		$table = $this->wrap($query->from);

		// Each column in the UPDATE statement needs to be wrapped in keyword
		// identifiers, and a place-holder needs to be created for each value
		// in the array of bindings. Of course, if the value of the binding
		// is an expression, the expression string will be injected.
		foreach ($values as $column => $value)
		{
			$columns[] = $this->wrap($column).' = '.$this->parameter($value);
		}

		$columns = implode(', ', $columns);

		// UPDATE statements may be constrained by a WHERE clause, so we'll
		// run the entire where compilation process for those contraints.
		// This is easily achieved by passing the query to the "wheres"
		// method which will call all of the where compilers.
		return trim("UPDATE {$table} SET {$columns} ".$this->wheres($query));
	}

	/**
	 * Compile a SQL DELETE statment from a Query instance.
	 *
	 * @param  Query   $query
	 * @return string
	 */
	public function delete(Query $query)
	{
		$table = $this->wrap($query->from);

		// Like the UPDATE statement, the DELETE statement is constrained
		// by WHERE clauses, so we'll need to run the "wheres" method to
		// make the WHERE clauses for the query. The "wheres" method 
		// encapsulates the logic to create the full WHERE clause.
		return trim("DELETE FROM {$table} ".$this->wheres($query));
	}

	/**
	 * Wrap a value in keyword identifiers.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public function wrap($value)
	{
		// If the value being wrapped contains a column alias, we need to
		// wrap it a little differently as each segment must be wrapped
		// and not the entire string. We'll split the value on the "as"
		// joiner to extract the column and the alias.
		if (strpos(strtolower($value), ' as ') !== false)
		{
			$segments = explode(' ', $value);

			return $this->wrap($segments[0]).' AS '.$this->wrap($segments[2]);
		}

		// Expressions should be injected into the query as raw strings,
		// so we do not want to wrap them in any way. We'll just return
		// the string value from the expression to be included.
		if ($value instanceof Expression) return $value->get();

		// Since columns may be prefixed with their corresponding table
		// name so as to not make them ambiguous, we will need to wrap
		// the table and the column in keyword identifiers.
		foreach (explode('.', $value) as $segment)
		{
			if ($segment == '*')
			{
				$wrapped[] = $segment;
			}
			else
			{
				$wrapped[] = $this->wrapper.$segment.$this->wrapper;
			}
		}

		return implode('.', $wrapped);
	}

	/**
	 * Create query parameters from an array of values.
	 *
	 * <code>
	 *		Returns "?, ?, ?", which may be used as PDO place-holders
	 *		$parameters = $grammar->parameterize(array(1, 2, 3));
	 *
	 *		// Returns "?, "Taylor"" since an expression is used
	 *		$parameters = $grammar->parameterize(array(1, DB::raw('Taylor')));
	 * </code>
	 *
	 * @param  array   $values
	 * @return string
	 */
	final public function parameterize($values)
	{
		return implode(', ', array_map(array($this, 'parameter'), $values));
	}

	/**
	 * Get the appropriate query parameter string for a value.
	 *
	 * <code>
	 *		// Returns a "?" PDO place-holder
	 *		$value = $grammar->parameter('Taylor Otwell');
	 *
	 *		// Returns "Taylor Otwell" as the raw value of the expression
	 *		$value = $grammar->parameter(DB::raw('Taylor Otwell'));
	 * </code>
	 *
	 * @param  mixed   $value
	 * @return string
	 */
	final public function parameter($value)
	{
		return ($value instanceof Expression) ? $value->get() : '?';
	}

	/**
	 * Create a comma-delimited list of wrapped column names.
	 *
	 * <code>
	 *		// Returns ""Taylor", "Otwell"" when the identifier is quotes
	 *		$columns = $grammar->columnize(array('Taylor', 'Otwell'));
	 * </code>
	 *
	 * @param  array   $columns
	 * @return string
	 */
	final public function columnize($columns)
	{
		return implode(', ', array_map(array($this, 'wrap'), $columns));
	}

}
const jakartaParts = (value) => {
  if (!value) return null

  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(new Date(value))
  const get = (type) => parts.find((part) => part.type === type)?.value

  return {
    date: `${get('year')}-${get('month')}-${get('day')}`,
    time: `${get('hour')}:${get('minute')}`,
  }
}

export const groupItemBorrowingSchedules = (rows = []) => {
  const groups = new Map()

  for (const row of rows) {
    const start = jakartaParts(row.borrow_date)
    const end = jakartaParts(row.return_date)
    if (!start || !end) continue

    if (start.date !== end.date) {
      groups.set(`range-${row.id}`, {
        key: `range-${row.id}`,
        mode: 'range',
        item: row.item,
        item_id: row.item_id,
        quantity: Number(row.quantity ?? 0),
        borrow_date: row.borrow_date,
        return_date: row.return_date,
        dates: [],
        start_time: start.time,
        end_time: end.time,
      })
      continue
    }

    const key = [
      'dates',
      row.item_id,
      row.quantity,
      start.time,
      end.time,
    ].join('-')
    const group = groups.get(key) ?? {
      key,
      mode: 'dates',
      item: row.item,
      item_id: row.item_id,
      quantity: Number(row.quantity ?? 0),
      dates: [],
      start_time: start.time,
      end_time: end.time,
      borrow_date: row.borrow_date,
      return_date: row.return_date,
    }

    group.dates.push(start.date)
    group.dates = [...new Set(group.dates)].sort()
    groups.set(key, group)
  }

  return [...groups.values()].sort((left, right) =>
    String(left.borrow_date).localeCompare(String(right.borrow_date)),
  )
}

export const distinctItemNames = (rows = []) =>
  [...new Set(rows.map((row) => row.item?.name).filter(Boolean))]

export const totalDistinctItemQuantity = (rows = []) => {
  const rowsByItem = new Map()

  for (const row of rows) {
    const itemRows = rowsByItem.get(row.item_id) ?? []
    itemRows.push(row)
    rowsByItem.set(row.item_id, itemRows)
  }

  return [...rowsByItem.values()].reduce((total, itemRows) => {
    const events = itemRows.flatMap((row) => [
      { time: new Date(row.borrow_date).getTime(), quantity: Number(row.quantity ?? 0) },
      { time: new Date(row.return_date).getTime(), quantity: -Number(row.quantity ?? 0) },
    ]).sort((left, right) => left.time - right.time || left.quantity - right.quantity)
    let activeQuantity = 0
    let peakQuantity = 0

    for (const event of events) {
      activeQuantity += event.quantity
      peakQuantity = Math.max(peakQuantity, activeQuantity)
    }

    return total + peakQuantity
  }, 0)
}

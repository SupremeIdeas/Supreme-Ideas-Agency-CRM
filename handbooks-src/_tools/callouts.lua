-- Naara handbook callouts + chapter labels (pandoc Lua filter, MASTER-ONLY tooling).
local titles = {
  verify = 'VERIFY BOX', example = 'EXAMPLE (INVENTED)', plain = 'IN PLAIN WORDS', checklist = 'CHECKLIST',
  insight = 'NAARA INSIGHT', next = 'DO THIS NEXT', warn = 'WATCH OUT', figure = nil,
}

function Div(el)
  for _, c in ipairs(el.classes) do
    if titles[c] then
      local t = titles[c]
      local extra = el.attributes['title']
      if extra and extra ~= '' then
        if c == 'verify' or c == 'insight' or c == 'next' then t = extra else t = t .. ': ' .. extra end
      end
      local head = pandoc.Div({ pandoc.Para({ pandoc.Str(string.upper(t)) }) }, pandoc.Attr('', { 'box-title' }))
      local body = { head }
      for _, b in ipairs(el.content) do table.insert(body, b) end
      return pandoc.Div(body, pandoc.Attr(el.identifier, { 'box', 'box-' .. c }))
    end
    if c == 'src' then return pandoc.Div(el.content, pandoc.Attr('', { 'source-note' })) end
  end
end

function Header(h)
  if h.level == 1 and h.classes:includes('part') then
    local num = h.attributes['number'] or ''
    local sub = h.attributes['sub'] or ''
    local id = h.identifier
    h.attributes['number'] = nil; h.attributes['sub'] = nil
    h.classes = pandoc.List()
    return pandoc.Div({
      pandoc.Div({ pandoc.Para({ pandoc.Str('PART ' .. num) }) }, pandoc.Attr('', { 'part-label' })),
      h,
      pandoc.Div({ pandoc.Para({ pandoc.Emph({ pandoc.Str(sub) }) }) }, pandoc.Attr('', { 'part-sub' })),
    }, pandoc.Attr('', { 'part' }))
  end
  if h.level == 2 and h.attributes['number'] then
    local label = pandoc.RawInline('html', '<span class="ch-label">CHAPTER ' .. h.attributes['number'] .. '</span>')
    table.insert(h.content, 1, label)
    h.attributes['number'] = nil
    return h
  end
end

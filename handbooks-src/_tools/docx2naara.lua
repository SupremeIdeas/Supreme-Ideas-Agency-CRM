-- One-off importer: owner's DOCX draft -> Naara handbook Markdown (callout tables become ::: divs, VERIFY marks become spans).
local map = {
  { 'VERIFY BOX', 'verify' }, { 'CHECKLIST', 'checklist' }, { 'IN PLAIN WORDS', 'plain' }, { 'EXAMPLE', 'example' },
  { 'NAARA INSIGHT', 'insight' }, { 'DO THIS NEXT', 'next' }, { 'DECISION TREE', 'plain' }, { 'RED FLAG', 'warn' }, { 'WATCH OUT', 'warn' },
}
local function text(b) return pandoc.utils.stringify(b) end

function Table(tbl)
  -- single-column "box" tables
  if #tbl.colspecs ~= 1 then return nil end
  local rows = {}
  for _, r in ipairs(tbl.bodies[1] and tbl.bodies[1].body or {}) do table.insert(rows, r) end
  local blocks = {}
  if tbl.head and tbl.head.rows then for _, r in ipairs(tbl.head.rows) do table.insert(rows, 1, r) end end
  for _, r in ipairs(rows) do for _, c in ipairs(r.cells) do for _, b in ipairs(c.contents) do table.insert(blocks, b) end end end
  if #blocks == 0 then return nil end
  local first = string.upper(text(blocks[1]))
  for _, m in ipairs(map) do
    if first:sub(1, #m[1]) == m[1] then
      local title = text(blocks[1]):gsub('^[^:]*:%s*', '')
      if text(blocks[1]):find(':') == nil then title = '' end
      table.remove(blocks, 1)
      -- checklist lines "☐ x" -> bullet list
      if m[2] == 'checklist' then
        local items = {}
        for _, b in ipairs(blocks) do
          local t = text(b):gsub('^☐%s*', '')
          local inl = {}
          for _, i in ipairs(b.content or {}) do table.insert(inl, i) end
          if inl[1] and inl[1].t == 'Str' and inl[1].text == '☐' then table.remove(inl, 1); if inl[1] and inl[1].t == 'Space' then table.remove(inl, 1) end end
          table.insert(items, { pandoc.Plain(inl) })
        end
        blocks = { pandoc.BulletList(items) }
      end
      local attrs = {}
      if title ~= '' and (m[2] == 'plain' or m[2] == 'example' or m[2] == 'checklist') then attrs.title = title:sub(1,1) .. title:sub(2):lower() end
      return pandoc.Div(blocks, pandoc.Attr('', { m[2] }, attrs))
    end
  end
end

function Emph(e)
  local s = text(e)
  if s:sub(1, 7) == '[VERIFY' then return pandoc.Span(e.content, pandoc.Attr('', { 'v' })) end
end

function Para(p)
  -- an all-italic paragraph that starts with "Source" is a source note
  if #p.content == 1 and p.content[1].t == 'Emph' then
    local s = text(p)
    if s:match('^Sources?:') or s:match('^Compiled') then return pandoc.Div({ pandoc.Para(p.content[1].content) }, pandoc.Attr('', { 'src' })) end
  end
end

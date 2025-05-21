async function loadContentForLLMFunction (event) {
  event.preventDefault()
  const atag = event.currentTarget
  const url = atag.href
  const parent = atag.closest('div')

  if (!url || !parent) return
  let html = ''
  try {
    const response = await fetch(url, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    if (!response.ok) throw new Error(`HTTP error ${response.status}`)
    html = await response.text()
  } catch (error) {
    console.error('Failed to load content:', error)
    html = '<p style="color: red;">Error loading content.</p>'
  }
  const wrapper = document.createElement('div')
  wrapper.innerHTML = html

  parent.replaceWith(wrapper.firstElementChild || wrapper)
}

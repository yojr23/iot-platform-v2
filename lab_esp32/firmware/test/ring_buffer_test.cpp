// Run: g++ -std=c++17 -I../do_sensor_node ring_buffer_test.cpp -o /tmp/rb_test && /tmp/rb_test
#include <cassert>
#include <cstdio>
#include "ring_buffer.h"

int main() {
  RingBuffer<int, 3> b;
  assert(b.empty() && !b.full() && b.size() == 0 && b.dropped() == 0);
  b.pop();  // pop en vacio: no-op
  assert(b.empty());

  b.push(1); b.push(2);
  assert(b.size() == 2 && !b.full() && b.front() == 1);
  b.push(3);
  assert(b.full());
  // FIFO
  assert(b.front() == 1); b.pop();
  assert(b.front() == 2); b.pop();
  assert(b.front() == 3); b.pop();
  assert(b.empty() && b.dropped() == 0);

  // overflow: descarta el mas viejo y cuenta
  for (int i = 1; i <= 5; i++) b.push(i);  // 1,2 descartados
  assert(b.size() == 3 && b.dropped() == 2);
  assert(b.front() == 3); b.pop();
  assert(b.front() == 4); b.pop();
  assert(b.front() == 5); b.pop();
  assert(b.empty() && b.dropped() == 2);

  // wrap-around tras vaciar parcialmente
  b.push(10); b.push(11); b.pop(); b.push(12); b.push(13);
  assert(b.size() == 3 && b.dropped() == 2);
  assert(b.front() == 11); b.pop();
  assert(b.front() == 12); b.pop();
  assert(b.front() == 13);
  puts("ring_buffer_test OK");
  return 0;
}
